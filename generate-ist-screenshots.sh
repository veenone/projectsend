#!/bin/bash
# Generate screenshots for IST theme and templates
# Requires: wkhtmltoimage

BASE_URL="http://localhost/projectsend"

echo "IST Screenshot Generator"
echo "========================"
echo ""

# Check if wkhtmltoimage is installed
if ! command -v wkhtmltoimage &> /dev/null; then
    echo "Error: wkhtmltoimage is not installed"
    echo "Install with: sudo apt-get install wkhtmltopdf"
    exit 1
fi

echo "1. Generating IST System Theme Screenshot..."
echo "   This requires admin interface to be accessible"
echo "   URL: ${BASE_URL}/manage-files.php"
echo ""
echo "   Please make sure you're logged in as admin in your browser first"
echo "   Then run this command manually:"
echo ""
echo "   wkhtmltoimage --width 1200 --height 800 \\"
echo "     --cookie 'PHPSESSID' 'YOUR_SESSION_ID' \\"
echo "     '${BASE_URL}/manage-files.php' \\"
echo "     /var/www/projectsend/systemtemplates/ist/screenshot.png"
echo ""
echo "   To get your session ID:"
echo "   - Open browser DevTools (F12)"
echo "   - Go to Application/Storage > Cookies"
echo "   - Copy PHPSESSID value"
echo ""

echo "2. Generating IST Public Template Screenshot..."
echo "   This captures the public files page"
echo ""

# Generate public template screenshot (no auth needed if public)
wkhtmltoimage \
    --width 1200 \
    --height 800 \
    --quality 95 \
    "${BASE_URL}/public.php" \
    /var/www/projectsend/templates/ist/screenshot.png

if [ $? -eq 0 ]; then
    echo "   ✓ Public template screenshot generated successfully"
    echo "   Location: /var/www/projectsend/templates/ist/screenshot.png"
else
    echo "   ✗ Failed to generate public template screenshot"
    echo ""
    echo "   Alternative: Use public-download.php with a public token:"
    echo "   wkhtmltoimage --width 1200 --height 800 \\"
    echo "     '${BASE_URL}/download.php?id=FILE_ID&token=PUBLIC_TOKEN' \\"
    echo "     /var/www/projectsend/templates/ist/screenshot.png"
fi

echo ""
echo "3. Email Template Screenshot (already generated)"
echo "   Location: /var/www/projectsend/emails/templates/ist/screenshot.png"
echo ""

echo "Done! Remember to commit the screenshots to git."
