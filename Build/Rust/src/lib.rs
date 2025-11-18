use libc::{c_char, c_int};
use quick_xml::events::Event;
use quick_xml::Reader;
use std::cell::RefCell;
use std::ffi::{CStr, CString};
use std::fs::File;
use std::io::BufReader;
use std::panic;
use std::path::Path;
use std::ptr;

// Database import module
pub mod db_import;

// Error codes
const SUCCESS: c_int = 0;
const ERR_FILE_NOT_FOUND: c_int = -1;
const ERR_PARSE_ERROR: c_int = -2;
const ERR_INVALID_UTF8: c_int = -3;
const ERR_MEMORY_ERROR: c_int = -4;
const ERR_PANIC: c_int = -5;

// Safety limits
const MAX_FILE_SIZE: u64 = 100 * 1024 * 1024; // 100MB
const MAX_TRANSLATIONS: usize = 1_000_000; // 1M entries

thread_local! {
    static LAST_ERROR: RefCell<Option<String>> = RefCell::new(None);
}

/// Stores error message for retrieval via xliff_get_last_error
fn set_last_error(err: String) {
    LAST_ERROR.with(|e| {
        *e.borrow_mut() = Some(err);
    });
}

/// FFI-safe translation structure
#[repr(C)]
pub struct Translation {
    /// Translation key in format: "component|type|placeholder"
    pub key: *mut c_char,
    /// Translated value
    pub value: *mut c_char,
}

/// FFI-safe array of translations
#[repr(C)]
pub struct TranslationArray {
    pub items: *mut Translation,
    pub count: usize,
}

/// Internal Rust representation
struct TranslationEntry {
    key: String,
    value: String,
}

/// Parse XLIFF file and extract translations
///
/// # Safety
/// - file_path must be a valid null-terminated C string
/// - out must be a valid pointer to write the result
/// - Caller must call xliff_free_translations() to free memory
#[no_mangle]
pub unsafe extern "C" fn xliff_parse_file(
    file_path: *const c_char,
    out: *mut *mut TranslationArray,
) -> c_int {
    let result = panic::catch_unwind(|| {
        xliff_parse_file_impl(file_path, out)
    });

    match result {
        Ok(code) => code,
        Err(_) => {
            set_last_error("Panic occurred during XLIFF parsing".to_string());
            ERR_PANIC
        }
    }
}

/// Internal implementation (panic boundary is in xliff_parse_file)
unsafe fn xliff_parse_file_impl(
    file_path: *const c_char,
    out: *mut *mut TranslationArray,
) -> c_int {
    // Validate inputs
    if file_path.is_null() || out.is_null() {
        set_last_error("Null pointer argument".to_string());
        return ERR_MEMORY_ERROR;
    }

    // Convert C string to Rust
    let c_str = match CStr::from_ptr(file_path).to_str() {
        Ok(s) => s,
        Err(_) => {
            set_last_error("Invalid UTF-8 in file path".to_string());
            return ERR_INVALID_UTF8;
        }
    };

    let path = Path::new(c_str);

    // Check file exists and size
    if !path.exists() {
        set_last_error(format!("File not found: {}", c_str));
        return ERR_FILE_NOT_FOUND;
    }

    match path.metadata() {
        Ok(metadata) => {
            if metadata.len() > MAX_FILE_SIZE {
                set_last_error(format!(
                    "File too large: {} bytes (max: {} bytes)",
                    metadata.len(),
                    MAX_FILE_SIZE
                ));
                return ERR_PARSE_ERROR;
            }
        }
        Err(e) => {
            set_last_error(format!("Cannot read file metadata: {}", e));
            return ERR_FILE_NOT_FOUND;
        }
    }

    // Parse XLIFF
    let translations = match parse_xliff_internal(path) {
        Ok(t) => t,
        Err(e) => {
            set_last_error(format!("Parse error: {}", e));
            return ERR_PARSE_ERROR;
        }
    };

    // Check translation count limit
    if translations.len() > MAX_TRANSLATIONS {
        set_last_error(format!(
            "Too many translations: {} (max: {})",
            translations.len(),
            MAX_TRANSLATIONS
        ));
        return ERR_PARSE_ERROR;
    }

    // Convert to FFI structure
    match translations_to_ffi(translations) {
        Ok(array_ptr) => {
            *out = array_ptr;
            SUCCESS
        }
        Err(e) => {
            set_last_error(format!("Memory allocation error: {}", e));
            ERR_MEMORY_ERROR
        }
    }
}

/// Parse XLIFF file using quick-xml with performance optimizations
pub(crate) fn parse_xliff_internal(path: &Path) -> Result<Vec<TranslationEntry>, String> {
    let file = File::open(path).map_err(|e| e.to_string())?;

    // Phase 1 Optimization: Increase buffer size from default (8KB) to 1MB
    // Reduces syscalls and improves I/O performance
    let file_reader = BufReader::with_capacity(1024 * 1024, file);
    let mut reader = Reader::from_reader(file_reader);
    reader.config_mut().trim_text(true);

    // Phase 1 Optimization: Pre-allocate capacity for large files
    // Typical file has ~419K translations, start with reasonable capacity
    let mut translations = Vec::with_capacity(50_000);

    // Phase 1 Optimization: Pre-allocate event buffer with larger size
    // Reduces reallocations during parsing
    let mut buf = Vec::with_capacity(4096);

    let mut in_trans_unit = false;
    let mut current_id = String::with_capacity(128);
    let mut current_target = String::with_capacity(256);
    let mut in_target = false;

    loop {
        match reader.read_event_into(&mut buf) {
            Ok(Event::Start(ref e)) => {
                match e.name().as_ref() {
                    b"trans-unit" => {
                        in_trans_unit = true;
                        current_id.clear();
                        current_target.clear();

                        // Phase 1 Optimization: Use from_utf8 instead of from_utf8_lossy
                        // Faster path when we know input is valid UTF-8
                        for attr in e.attributes() {
                            if let Ok(attr) = attr {
                                if attr.key.as_ref() == b"id" {
                                    if let Ok(id_str) = std::str::from_utf8(&attr.value) {
                                        current_id.push_str(id_str);
                                    } else {
                                        current_id = String::from_utf8_lossy(&attr.value).to_string();
                                    }
                                    break;
                                }
                            }
                        }
                    }
                    b"target" if in_trans_unit => {
                        in_target = true;
                        current_target.clear();
                    }
                    _ => {}
                }
            }
            Ok(Event::Text(ref e)) if in_target => {
                current_target.push_str(&e.unescape().map_err(|e| e.to_string())?);
            }
            Ok(Event::End(ref e)) => {
                if e.name().as_ref() == b"target" {
                    in_target = false;
                } else if e.name().as_ref() == b"trans-unit" {
                    in_trans_unit = false;

                    if !current_id.is_empty() && !current_target.is_empty() {
                        translations.push(TranslationEntry {
                            key: current_id.clone(),
                            value: current_target.clone(),
                        });
                    }
                }
            }
            Ok(Event::Eof) => break,
            Err(e) => return Err(format!("XML parse error: {}", e)),
            _ => {}
        }
        buf.clear();
    }

    Ok(translations)
}

/// Convert Rust Vec to FFI array
fn translations_to_ffi(
    translations: Vec<TranslationEntry>,
) -> Result<*mut TranslationArray, String> {
    let count = translations.len();

    // Allocate Translation array
    let layout = std::alloc::Layout::array::<Translation>(count)
        .map_err(|e| format!("Layout error: {}", e))?;

    let items_ptr = unsafe { std::alloc::alloc(layout) as *mut Translation };
    if items_ptr.is_null() {
        return Err("Failed to allocate Translation array".to_string());
    }

    // Fill array
    for (i, entry) in translations.into_iter().enumerate() {
        let key_cstr = CString::new(entry.key).map_err(|e| e.to_string())?;
        let value_cstr = CString::new(entry.value).map_err(|e| e.to_string())?;

        unsafe {
            let item_ptr = items_ptr.add(i);
            (*item_ptr).key = key_cstr.into_raw();
            (*item_ptr).value = value_cstr.into_raw();
        }
    }

    // Allocate TranslationArray
    let array = Box::new(TranslationArray {
        items: items_ptr,
        count,
    });

    Ok(Box::into_raw(array))
}

/// Free memory allocated by xliff_parse_file
///
/// # Safety
/// - array must be a valid pointer returned by xliff_parse_file
/// - array must not be used after calling this function
#[no_mangle]
pub unsafe extern "C" fn xliff_free_translations(array: *mut TranslationArray) {
    if array.is_null() {
        return;
    }

    let array = Box::from_raw(array);

    if !array.items.is_null() {
        for i in 0..array.count {
            let item = &mut *array.items.add(i);

            if !item.key.is_null() {
                let _ = CString::from_raw(item.key);
            }
            if !item.value.is_null() {
                let _ = CString::from_raw(item.value);
            }
        }

        let layout = std::alloc::Layout::array::<Translation>(array.count).unwrap();
        std::alloc::dealloc(array.items as *mut u8, layout);
    }
}

/// Get last error message
///
/// # Safety
/// - Returns a null-terminated C string
/// - String is valid until next call to xliff_parse_file or xliff_get_last_error
/// - Do not free the returned pointer
#[no_mangle]
pub unsafe extern "C" fn xliff_get_last_error() -> *const c_char {
    LAST_ERROR.with(|e| {
        let error = e.borrow();
        match error.as_ref() {
            Some(err) => {
                let c_str = CString::new(err.as_str()).unwrap_or_else(|_| {
                    CString::new("Invalid UTF-8 in error message").unwrap()
                });
                c_str.into_raw() as *const c_char
            }
            None => ptr::null(),
        }
    })
}

/// Get library version
#[no_mangle]
pub extern "C" fn xliff_version() -> *const c_char {
    let version = concat!(env!("CARGO_PKG_VERSION"), "\0");
    version.as_ptr() as *const c_char
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_parse_valid_xliff() {
        let xliff_content = r#"<?xml version="1.0" encoding="UTF-8"?>
<xliff version="1.0">
  <file source-language="en" datatype="plaintext" original="messages" date="2024-01-01T12:00:00Z" product-name="local">
    <header/>
    <body>
      <trans-unit id="form|button|submit" xml:space="preserve">
        <source>Submit</source>
        <target>Absenden</target>
      </trans-unit>
      <trans-unit id="form|button|cancel" xml:space="preserve">
        <source>Cancel</source>
        <target>Abbrechen</target>
      </trans-unit>
    </body>
  </file>
</xliff>"#;

        let temp_file = std::env::temp_dir().join("test_xliff.xlf");
        std::fs::write(&temp_file, xliff_content).unwrap();

        let translations = parse_xliff_internal(&temp_file).unwrap();

        assert_eq!(translations.len(), 2);
        assert_eq!(translations[0].key, "form|button|submit");
        assert_eq!(translations[0].value, "Absenden");
        assert_eq!(translations[1].key, "form|button|cancel");
        assert_eq!(translations[1].value, "Abbrechen");

        std::fs::remove_file(temp_file).unwrap();
    }

    #[test]
    fn test_file_not_found() {
        let result = parse_xliff_internal(Path::new("/nonexistent/file.xlf"));
        assert!(result.is_err());
    }
}
