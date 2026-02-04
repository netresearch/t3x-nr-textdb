use mysql::prelude::*;
use mysql::{OptsBuilder, Pool, PooledConn};
use std::collections::HashMap;
use std::ffi::{CStr, CString};
use std::os::raw::{c_char, c_int};

/// Database configuration passed from PHP
#[repr(C)]
pub struct DbConfig {
    pub host: *const c_char,
    pub port: u16,
    pub database: *const c_char,
    pub username: *const c_char,
    pub password: *const c_char,
}

/// Import statistics returned to PHP
#[repr(C)]
pub struct ImportStats {
    pub total_processed: usize,
    pub inserted: usize,
    pub updated: usize,
    pub skipped: usize,
    pub errors: usize,
    pub duration_ms: u64,
}

/// Translation entry for bulk import
#[derive(Debug, Clone)]
struct TranslationEntry {
    environment: String,
    component: String,
    type_name: String,
    placeholder: String,
    translation: String,
    language_uid: i32,
}

/// Cache for database IDs to avoid repeated lookups
struct ImportCache {
    environments: HashMap<String, i32>,
    components: HashMap<String, i32>,
    types: HashMap<String, i32>,
    existing_translations: HashMap<String, i32>, // key: composite key, value: uid
}

impl ImportCache {
    fn new() -> Self {
        Self {
            environments: HashMap::new(),
            components: HashMap::new(),
            types: HashMap::new(),
            existing_translations: HashMap::new(),
        }
    }

    /// Preload all environments, components, and types in batch
    fn preload(&mut self, conn: &mut PooledConn) -> Result<(), mysql::Error> {
        // Load environments
        let envs: Vec<(i32, String)> =
            conn.query("SELECT uid, name FROM tx_nrtextdb_domain_model_environment")?;
        for (uid, name) in envs {
            self.environments.insert(name, uid);
        }

        // Load components
        let comps: Vec<(i32, String)> =
            conn.query("SELECT uid, name FROM tx_nrtextdb_domain_model_component")?;
        for (uid, name) in comps {
            self.components.insert(name, uid);
        }

        // Load types
        let types: Vec<(i32, String)> =
            conn.query("SELECT uid, name FROM tx_nrtextdb_domain_model_type")?;
        for (uid, name) in types {
            self.types.insert(name, uid);
        }

        Ok(())
    }

    /// Get or create environment ID
    fn get_or_create_environment(
        &mut self,
        conn: &mut PooledConn,
        name: &str,
    ) -> Result<i32, mysql::Error> {
        if let Some(&uid) = self.environments.get(name) {
            return Ok(uid);
        }

        // Insert new environment
        conn.exec_drop(
            "INSERT INTO tx_nrtextdb_domain_model_environment (name, tstamp, crdate) VALUES (?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())",
            (name,)
        )?;

        let uid = conn.last_insert_id() as i32;
        self.environments.insert(name.to_string(), uid);
        Ok(uid)
    }

    /// Get or create component ID
    fn get_or_create_component(
        &mut self,
        conn: &mut PooledConn,
        name: &str,
    ) -> Result<i32, mysql::Error> {
        if let Some(&uid) = self.components.get(name) {
            return Ok(uid);
        }

        conn.exec_drop(
            "INSERT INTO tx_nrtextdb_domain_model_component (name, tstamp, crdate) VALUES (?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())",
            (name,)
        )?;

        let uid = conn.last_insert_id() as i32;
        self.components.insert(name.to_string(), uid);
        Ok(uid)
    }

    /// Get or create type ID
    fn get_or_create_type(
        &mut self,
        conn: &mut PooledConn,
        name: &str,
    ) -> Result<i32, mysql::Error> {
        if let Some(&uid) = self.types.get(name) {
            return Ok(uid);
        }

        conn.exec_drop(
            "INSERT INTO tx_nrtextdb_domain_model_type (name, tstamp, crdate) VALUES (?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())",
            (name,)
        )?;

        let uid = conn.last_insert_id() as i32;
        self.types.insert(name.to_string(), uid);
        Ok(uid)
    }
}

/// Bulk import translations to database
///
/// # Safety
/// - config must be a valid pointer with valid C strings
/// - translations must be a valid array
/// - out_stats must be a valid pointer
#[no_mangle]
pub unsafe extern "C" fn xliff_db_import(
    config: *const DbConfig,
    translations: *const *const c_char, // Array of "env|component|type|placeholder|translation|lang_uid"
    count: usize,
    out_stats: *mut ImportStats,
) -> c_int {
    let result = std::panic::catch_unwind(|| xliff_db_import_impl(config, translations, count, out_stats));

    match result {
        Ok(code) => code,
        Err(_) => -1,
    }
}

unsafe fn xliff_db_import_impl(
    config: *const DbConfig,
    translations: *const *const c_char,
    count: usize,
    out_stats: *mut ImportStats,
) -> c_int {
    if config.is_null() || translations.is_null() || out_stats.is_null() {
        return -1;
    }

    let start = std::time::Instant::now();

    // Parse database config
    let db_config = &*config;
    let host = CStr::from_ptr(db_config.host).to_str().unwrap_or("localhost");
    let database = CStr::from_ptr(db_config.database)
        .to_str()
        .unwrap_or("typo3");
    let username = CStr::from_ptr(db_config.username).to_str().unwrap_or("root");
    let password = CStr::from_ptr(db_config.password).to_str().unwrap_or("");

    // Create database connection
    let opts = OptsBuilder::new()
        .ip_or_hostname(Some(host))
        .tcp_port(db_config.port)
        .db_name(Some(database))
        .user(Some(username))
        .pass(Some(password));

    let pool = match Pool::new(opts) {
        Ok(p) => p,
        Err(_) => return -2, // Connection error
    };

    let mut conn = match pool.get_conn() {
        Ok(c) => c,
        Err(_) => return -2,
    };

    // Parse translation entries
    let mut entries = Vec::new();
    for i in 0..count {
        let entry_str = CStr::from_ptr(*translations.add(i))
            .to_str()
            .unwrap_or("");

        let parts: Vec<&str> = entry_str.split('|').collect();
        if parts.len() == 6 {
            entries.push(TranslationEntry {
                environment: parts[0].to_string(),
                component: parts[1].to_string(),
                type_name: parts[2].to_string(),
                placeholder: parts[3].to_string(),
                translation: parts[4].to_string(),
                language_uid: parts[5].parse().unwrap_or(0),
            });
        }
    }

    // Perform import
    let mut stats = ImportStats {
        total_processed: entries.len(),
        inserted: 0,
        updated: 0,
        skipped: 0,
        errors: 0,
        duration_ms: 0,
    };

    if let Err(_) = import_translations(&mut conn, &entries, &mut stats) {
        return -3; // Import error
    }

    stats.duration_ms = start.elapsed().as_millis() as u64;
    *out_stats = stats;

    0 // Success
}

/// Perform the actual bulk import
fn import_translations(
    conn: &mut PooledConn,
    entries: &[TranslationEntry],
    stats: &mut ImportStats,
) -> Result<(), mysql::Error> {
    // Start transaction
    conn.query_drop("START TRANSACTION")?;

    // Initialize cache and preload
    let mut cache = ImportCache::new();
    cache.preload(conn)?;

    // Resolve all foreign keys first
    let mut resolved_entries = Vec::new();

    for entry in entries {
        let env_uid = cache.get_or_create_environment(conn, &entry.environment)?;
        let comp_uid = cache.get_or_create_component(conn, &entry.component)?;
        let type_uid = cache.get_or_create_type(conn, &entry.type_name)?;

        resolved_entries.push((
            env_uid,
            comp_uid,
            type_uid,
            &entry.placeholder,
            &entry.translation,
            entry.language_uid,
        ));
    }

    // Check existing translations in batches to avoid max_allowed_packet issues
    let mut existing_map: HashMap<String, (i32, i32, i32, i32)> = HashMap::new();
    let placeholders: Vec<&str> = entries.iter().map(|e| e.placeholder.as_str()).collect();

    // Process in chunks of 1000 to stay under MySQL packet limits
    const LOOKUP_BATCH_SIZE: usize = 1000;
    for chunk in placeholders.chunks(LOOKUP_BATCH_SIZE) {
        let existing: Vec<(String, i32, i32, i32, i32, i32)> = conn.exec(
            format!(
                "SELECT CONCAT(environment, '|', component, '|', type, '|', placeholder, '|', sys_language_uid),
                        uid, environment, component, type, sys_language_uid
                 FROM tx_nrtextdb_domain_model_translation
                 WHERE placeholder IN ({})",
                chunk.iter().map(|_| "?").collect::<Vec<_>>().join(",")
            ),
            chunk.to_vec(),
        )?;

        for (key, uid, env, comp, typ, _lang) in existing {
            existing_map.insert(key, (uid, env, comp, typ));
        }
    }

    // Bulk INSERT for new translations
    let mut insert_batch = Vec::new();
    let mut update_batch = Vec::new();

    for (env_uid, comp_uid, type_uid, placeholder, translation, lang_uid) in resolved_entries {
        let key = format!("{}|{}|{}|{}|{}", env_uid, comp_uid, type_uid, placeholder, lang_uid);

        if let Some(&(uid, _, _, _)) = existing_map.get(&key) {
            // Needs update
            update_batch.push((translation, uid));
        } else {
            // Needs insert
            insert_batch.push((
                env_uid,
                comp_uid,
                type_uid,
                placeholder,
                translation,
                lang_uid,
            ));
        }
    }

    // Bulk INSERT (500 rows at a time)
    const BATCH_SIZE: usize = 500;
    for chunk in insert_batch.chunks(BATCH_SIZE) {
        let placeholders = chunk
            .iter()
            .map(|_| "(?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())")
            .collect::<Vec<_>>()
            .join(",");

        let mut params: Vec<mysql::Value> = Vec::new();
        for (env, comp, typ, ph, trans, lang) in chunk {
            params.push((*env).into());
            params.push((*comp).into());
            params.push((*typ).into());
            params.push((*ph).into());
            params.push((*trans).into());
            params.push((*lang).into());
        }

        conn.exec_drop(
            format!(
                "INSERT INTO tx_nrtextdb_domain_model_translation
                 (environment, component, type, placeholder, value, sys_language_uid, tstamp, crdate)
                 VALUES {}",
                placeholders
            ),
            params,
        )?;

        stats.inserted += chunk.len();
    }

    // Bulk UPDATE using CASE-WHEN pattern (same as PHP ImportService.php:346-377)
    // This batches multiple UPDATEs into a single query for massive performance gain
    for chunk in update_batch.chunks(BATCH_SIZE) {
        if chunk.is_empty() {
            continue;
        }

        // Build CASE-WHEN expressions for value and tstamp
        let mut value_cases = Vec::new();
        let mut uids = Vec::new();
        let mut params: Vec<mysql::Value> = Vec::new();

        for (translation, uid) in chunk {
            value_cases.push(format!("WHEN {} THEN ?", uid));
            uids.push(*uid);
            params.push((*translation).into());
        }

        // Add UIDs for WHERE IN clause
        for uid in &uids {
            params.push((*uid).into());
        }

        let sql = format!(
            "UPDATE tx_nrtextdb_domain_model_translation
             SET value = (CASE uid {} END),
                 tstamp = UNIX_TIMESTAMP()
             WHERE uid IN ({})",
            value_cases.join(" "),
            uids.iter().map(|_| "?").collect::<Vec<_>>().join(",")
        );

        conn.exec_drop(sql, params)?;
        stats.updated += chunk.len();
    }

    // Commit transaction
    conn.query_drop("COMMIT")?;

    Ok(())
}

/// Combined XLIFF parse + database import (optimal pipeline)
///
/// # Safety
/// - file_path must be a valid null-terminated C string
/// - config must be a valid pointer with valid C strings
/// - out_stats must be a valid pointer
#[no_mangle]
pub unsafe extern "C" fn xliff_import_file_to_db(
    file_path: *const c_char,
    config: *const DbConfig,
    environment: *const c_char,
    language_uid: i32,
    out_stats: *mut ImportStats,
) -> c_int {
    let result = std::panic::catch_unwind(|| {
        xliff_import_file_to_db_impl(file_path, config, environment, language_uid, out_stats)
    });

    match result {
        Ok(code) => code,
        Err(_) => -1,
    }
}

unsafe fn xliff_import_file_to_db_impl(
    file_path: *const c_char,
    config: *const DbConfig,
    environment_name: *const c_char,
    language_uid: i32,
    out_stats: *mut ImportStats,
) -> c_int {
    if file_path.is_null() || config.is_null() || environment_name.is_null() || out_stats.is_null() {
        return -1;
    }

    let start = std::time::Instant::now();

    // Parse XLIFF file using existing parser
    let file_path_str = match CStr::from_ptr(file_path).to_str() {
        Ok(s) => s,
        Err(_) => return -3, // UTF-8 error
    };

    let environment_str = match CStr::from_ptr(environment_name).to_str() {
        Ok(s) => s,
        Err(_) => return -3,
    };

    // Parse XLIFF
    use crate::parse_xliff_internal;
    use std::path::Path;

    let parse_start = std::time::Instant::now();
    let translations = match parse_xliff_internal(Path::new(file_path_str)) {
        Ok(t) => t,
        Err(_) => return -2, // Parse error
    };
    let parse_duration = parse_start.elapsed();
    eprintln!("[TIMING] XLIFF parsing: {} ms ({} translations)",
        parse_duration.as_millis(), translations.len());

    // Convert XLIFF entries to database import format
    let convert_start = std::time::Instant::now();
    let mut db_entries = Vec::new();
    for trans in &translations {
        // Parse key format: "component|type|placeholder"
        let parts: Vec<&str> = trans.key.split('|').collect();
        if parts.len() == 3 {
            db_entries.push(TranslationEntry {
                environment: environment_str.to_string(),
                component: parts[0].to_string(),
                type_name: parts[1].to_string(),
                placeholder: parts[2].to_string(),
                translation: trans.value.clone(),
                language_uid,
            });
        }
    }
    let convert_duration = convert_start.elapsed();
    eprintln!("[TIMING] Data conversion: {} ms ({} entries)",
        convert_duration.as_millis(), db_entries.len());

    // Parse database config
    let db_config = &*config;
    let host = CStr::from_ptr(db_config.host).to_str().unwrap_or("localhost");
    let database = CStr::from_ptr(db_config.database).to_str().unwrap_or("typo3");
    let username = CStr::from_ptr(db_config.username).to_str().unwrap_or("root");
    let password = CStr::from_ptr(db_config.password).to_str().unwrap_or("");

    // Create database connection
    let opts = OptsBuilder::new()
        .ip_or_hostname(Some(host))
        .tcp_port(db_config.port)
        .db_name(Some(database))
        .user(Some(username))
        .pass(Some(password));

    let pool = match Pool::new(opts) {
        Ok(p) => p,
        Err(_) => return -2, // Connection error
    };

    let mut conn = match pool.get_conn() {
        Ok(c) => c,
        Err(_) => return -2,
    };

    // Perform import
    let mut stats = ImportStats {
        total_processed: db_entries.len(),
        inserted: 0,
        updated: 0,
        skipped: 0,
        errors: 0,
        duration_ms: 0,
    };

    let db_start = std::time::Instant::now();
    if let Err(_) = import_translations(&mut conn, &db_entries, &mut stats) {
        return -3; // Import error
    }
    let db_duration = db_start.elapsed();
    eprintln!("[TIMING] Database import: {} ms ({} inserted, {} updated)",
        db_duration.as_millis(), stats.inserted, stats.updated);

    let total_duration = start.elapsed();
    eprintln!("[TIMING] Total pipeline: {} ms", total_duration.as_millis());
    eprintln!("[TIMING] Breakdown: parse={:.1}%, convert={:.1}%, db={:.1}%",
        100.0 * parse_duration.as_secs_f64() / total_duration.as_secs_f64(),
        100.0 * convert_duration.as_secs_f64() / total_duration.as_secs_f64(),
        100.0 * db_duration.as_secs_f64() / total_duration.as_secs_f64());

    stats.duration_ms = total_duration.as_millis() as u64;
    *out_stats = stats;

    0 // Success
}

/// Get library version
#[no_mangle]
pub extern "C" fn xliff_db_version() -> *const c_char {
    let version = concat!(env!("CARGO_PKG_VERSION"), "-db\0");
    version.as_ptr() as *const c_char
}
