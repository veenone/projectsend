# IST Screenshot Generation Guide

This guide explains how to generate screenshots for IST themes and templates.

## Prerequisites

- `wkhtmltoimage` installed (already done)
- ProjectSend running on the server
- Admin access for system theme screenshots

## Quick Start

Run the helper script:

```bash
cd /var/www/projectsend
./generate-ist-screenshots.sh
```

## Manual Screenshot Generation

### 1. IST System Theme Screenshot

The system theme screenshot shows the admin interface with IST branding.

**Method A: Using Browser Session Cookie**

1. Login to ProjectSend admin at: `http://10.8.8.106/projectsend/`
2. Open browser DevTools (F12)
3. Go to: Application/Storage → Cookies
4. Copy the `PHPSESSID` value
5. Run:

```bash
wkhtmltoimage \
  --width 1200 \
  --height 800 \
  --quality 95 \
  --cookie 'PHPSESSID' 'YOUR_SESSION_ID_HERE' \
  'http://10.8.8.106/projectsend/manage-files.php' \
  /var/www/projectsend/systemtemplates/ist/screenshot.png
```

**Method B: Manual Browser Screenshot**

1. Login to admin panel
2. Navigate to Files page (good example of IST theme)
3. Set browser window to ~1200px width
4. Press F12 → Toggle device toolbar (Ctrl+Shift+M)
5. Set to responsive, 1200x800
6. Right-click page → Capture screenshot
7. Save to: `/var/www/projectsend/systemtemplates/ist/screenshot.png`

**Best pages to capture for system theme:**
- `/manage-files.php` - Shows file management with IST theme
- `/options.php` - Shows settings page with IST styling
- `/dashboard.php` - Shows dashboard with IST colors

### 2. IST Public Template Screenshot

The public template screenshot shows the public file listing page.

**Automatic Generation:**

```bash
wkhtmltoimage \
  --width 1200 \
  --height 800 \
  --quality 95 \
  'http://10.8.8.106/projectsend/public.php' \
  /var/www/projectsend/templates/ist/screenshot.png
```

**If public.php requires login, use a public file download page:**

```bash
# First, get a public file ID and token from the database:
mysql -u root -p projectsend -e "SELECT id, public_token FROM tbl_files WHERE public_allow = 1 LIMIT 1;"

# Then use the values:
wkhtmltoimage \
  --width 1200 \
  --height 800 \
  --quality 95 \
  'http://10.8.8.106/projectsend/download.php?id=FILE_ID&token=PUBLIC_TOKEN' \
  /var/www/projectsend/templates/ist/screenshot.png
```

**Best pages to capture for public template:**
- `/public.php` - Shows public file grid with IST colors
- `/download.php?id=X&token=Y` - Shows download page with IST styling

### 3. IST Email Template Screenshot

Already generated! Located at:
- `/var/www/projectsend/emails/templates/ist/screenshot.png`

Used the preview file:
```bash
wkhtmltoimage \
  --width 800 \
  --quality 95 \
  '/var/www/projectsend/emails/templates/ist/preview.html' \
  '/var/www/projectsend/emails/templates/ist/screenshot.png'
```

## Screenshot Specifications

### System Theme Screenshot
- **Size**: 1200x800 pixels
- **Format**: PNG
- **Location**: `/var/www/projectsend/systemtemplates/ist/screenshot.png`
- **Content**: Admin interface showing IST gradient header, navigation, and content

### Public Template Screenshot
- **Size**: 1200x800 pixels
- **Format**: PNG
- **Location**: `/var/www/projectsend/templates/ist/screenshot.png`
- **Content**: Public file listing showing IST card grid layout

### Email Template Screenshot
- **Size**: 800px width (auto height)
- **Format**: PNG
- **Location**: `/var/www/projectsend/emails/templates/ist/screenshot.png`
- **Content**: Full email template with gradient header and footer

## Verify Screenshots

After generation, verify the screenshots:

```bash
# Check file sizes
ls -lh /var/www/projectsend/systemtemplates/ist/screenshot.png
ls -lh /var/www/projectsend/templates/ist/screenshot.png
ls -lh /var/www/projectsend/emails/templates/ist/screenshot.png

# View with image viewer
xdg-open /var/www/projectsend/systemtemplates/ist/screenshot.png
xdg-open /var/www/projectsend/templates/ist/screenshot.png
xdg-open /var/www/projectsend/emails/templates/ist/screenshot.png
```

## Commit to Git

After generating all screenshots:

```bash
cd /var/www/projectsend
git add systemtemplates/ist/screenshot.png
git add templates/ist/screenshot.png
git add emails/templates/ist/screenshot.png
git commit -m "Update IST theme and template screenshots with brand colors"
```

## Troubleshooting

### "Cannot connect" error
- Make sure Apache/Nginx is running
- Check ProjectSend is accessible at the URL
- Try using `localhost` instead of IP address

### "Access denied" error
- For system theme: Ensure session cookie is valid
- For public template: Use a public download URL instead

### Screenshot is blank
- Check if JavaScript is required (use `--enable-javascript`)
- Increase load time with `--javascript-delay 2000`
- Try a different page

### Screenshot has wrong colors
- Clear browser cache
- Make sure IST theme is selected in settings
- Check CSS files are properly loaded
