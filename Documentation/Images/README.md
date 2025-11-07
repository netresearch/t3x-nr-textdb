# Documentation Images

This directory should contain screenshots and images referenced in the documentation.

## Required Screenshots

The documentation references the following images that should be added:

### Introduction Section
- `screenshot-list.png` - Translation list view with filtering options
- `screenshot-edit.png` - Translation edit form
- `screenshot-import.png` - Import interface

### User Manual
- `user-module-access.png` - Location of TextDB module in backend
- `user-list-view.png` - Main translation list with filter options
- `user-edit-form.png` - Edit form for a single translation

## Guidelines

- Use PNG format for screenshots
- Recommended resolution: 1920x1080 or higher
- Crop to show relevant interface elements
- Use TYPO3 backend with default theme
- Include shadow if specified with `:class: with-shadow` directive

## Creating Screenshots

1. Open TYPO3 backend with TextDB extension
2. Navigate to relevant module/page
3. Take screenshot (use browser dev tools or screenshot tool)
4. Crop and optimize image
5. Save to this directory with descriptive filename
6. Reference in RST files with:

```rst
.. figure:: ../Images/your-screenshot.png
   :alt: Description of image
   :class: with-shadow
   
   Caption text
```

## Image Optimization

Optimize images before committing:

```bash
# Using ImageMagick
convert input.png -resize 1920x1080 -quality 85 output.png

# Using pngquant
pngquant --quality=80-95 input.png
```

## Notes

- Images are not strictly required for documentation to build
- RST files will show alt text if images are missing
- Add actual screenshots after extension is installed in a TYPO3 instance
