# Cairo Font Files - Local Setup

This directory contains the local Cairo font implementation for offline use.

The required TTF files are already included in this folder:

- `cairo-regular.ttf` (weight 400)
- `cairo-medium.ttf` (weight 500)
- `cairo-semibold.ttf` (weight 600)
- `cairo-bold.ttf` (weight 700)

## Optional Optimization

You can optionally add WOFF2 files for smaller font size:

- `cairo-regular.woff2`
- `cairo-medium.woff2`
- `cairo-semibold.woff2`
- `cairo-bold.woff2`

## Download Sources (Optional WOFF2)

If you want to add WOFF2 files, you can download Cairo from:

1. **Google Fonts** (recommended):
   - Visit: https://fonts.google.com/specimen/Cairo
   - Download the font family
   - Extract and convert to WOFF2 format using online converters

2. **Font Squirrel**:
   - Visit: https://www.fontsquirrel.com/fonts/cairo
   - Download the webfont kit

3. **Direct CDN Download**:
   - Open the Google Fonts CSS endpoint and copy the latest `woff2` URLs:
   - `https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap`

## File Naming Convention

If you add WOFF2 files, make sure naming matches weights:
- Weight 400 → `cairo-regular.woff2`
- Weight 500 → `cairo-medium.woff2`
- Weight 600 → `cairo-semibold.woff2`
- Weight 700 → `cairo-bold.woff2`

## Verification

After placing the font files, test the application offline to ensure:
1. Cairo font displays correctly
2. No font loading errors in browser console
3. Text renders properly in Arabic and English

## Benefits

Once completed, your application will:
- Work completely offline
- Load faster without external dependencies
- Have consistent font rendering
- Reduce external HTTP requests
