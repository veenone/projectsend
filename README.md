# ProjectSend

![ProjectSend logo](https://www.projectsend.org/projectsend-logo-new.png)

[![PHP Static Analysis](https://github.com/projectsend/projectsend/actions/workflows/php-static-analysis.yml/badge.svg)](https://github.com/projectsend/projectsend/actions/workflows/php-static-analysis.yml)
[![Asset Build Check](https://github.com/projectsend/projectsend/actions/workflows/asset-build.yml/badge.svg)](https://github.com/projectsend/projectsend/actions/workflows/asset-build.yml)
[![Dependency Security](https://github.com/projectsend/projectsend/actions/workflows/dependency-security.yml/badge.svg)](https://github.com/projectsend/projectsend/actions/workflows/dependency-security.yml)

## About

ProjectSend is a free, clients-oriented, private file sharing web application.

Clients are created and assigned a username and a password.  
Uploaded files can be assigned to specific clients or clients groups.

Other features include auto-expiration of upload, notifications, full logging of actions by users and clients, option to allow clients to also upload files, themes, multiple languages...

Main website: [projectsend.org](https://www.projectsend.org)  
git: [current repository](https://github.com/projectsend/projectsend/)
Support via Patreon: [Patreon](https://www.patreon.com/ignacionelson)
Support via Open Collective: [Open Collective](https://opencollective.com/projectsend)

Feel free to participate!

## IMPORTANT

It is recommended that you download the latest release from the official website.

Downloading a development version directly from the repository might give you unexpected results, such as visible errors, functions that are still not finished, etc.

## Documentation

Docs are maintained at <https://docs.projectsend.org>.
There you will find installation requirements, instructions, tutorials, and troubleshooting information.

## Developing

If you want to help with development, you will need to do a few things via the command line:

1. Download the npm and composer dependencies with the commands ````npm install```` and ````composer update````
1. Run the default gulp task simply with ````gulp```` to compile the main CSS and JS assets files.

## How to join the project

Questions, ideas?

Send your message to contact@projectsend.org or join us on our [Facebook page](https://www.facebook.com/projectsend/)

## Translations

Thanks. Arigatō. Danke. Gracias. Grazie. Mahadsanid. Salamat po. Merci. אַ דאַנק.

You can download the compiled, translated files for the available languages from [projectsend.org/translations](https://www.projectsend.org/translations/)

If you want to translate ProjectSend in your language or work on an existing translation, please join the project on [Transifex](https://www.transifex.com/projects/p/projectsend)

## ONLYOFFICE Document Editor Integration

ProjectSend supports integration with ONLYOFFICE Document Server for real-time editing of Office documents (docx, xlsx, pptx, etc.) directly in the browser.

### Deployment

#### 1. Install ONLYOFFICE Document Server using Docker

Create a `docker-compose-onlyoffice.yml` file:

```yaml
version: '3.8'
services:
  onlyoffice:
    image: onlyoffice/documentserver:latest
    container_name: projectsend-onlyoffice
    ports:
      - "8080:80"
    environment:
      - JWT_ENABLED=true
      - JWT_SECRET=your-strong-secret-key-here
      - DS_URL=/onlyoffice/
    volumes:
      - onlyoffice_data:/var/www/onlyoffice/Data
      - onlyoffice_logs:/var/log/onlyoffice
    restart: unless-stopped

volumes:
  onlyoffice_data:
  onlyoffice_logs:
```

Start the container:

```bash
docker-compose -f docker-compose-onlyoffice.yml up -d
```

#### 2. Configure Apache Reverse Proxy

Add to your Apache virtual host configuration:

```apache
# Enable required modules
# a2enmod proxy proxy_http proxy_wstunnel rewrite headers

# ONLYOFFICE reverse proxy
RewriteEngine On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/onlyoffice/(.*) ws://127.0.0.1:8080/$1 [P,L]

RewriteRule ^/onlyoffice$ /onlyoffice/ [R=301,L]

ProxyPass /onlyoffice http://127.0.0.1:8080
ProxyPassReverse /onlyoffice http://127.0.0.1:8080

<Location /onlyoffice>
    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "https"
</Location>
```

Restart Apache:

```bash
sudo systemctl restart apache2
```

#### 3. Configure ProjectSend

1. Go to Options > Document Editor in ProjectSend admin
2. Enable ONLYOFFICE integration
3. Set Document Server URL: `https://your-domain.com/onlyoffice`
4. Set JWT Secret (must match `JWT_SECRET` in docker-compose)
5. Enable JWT authentication
6. Test connection

### User Guide

#### Viewing Documents

1. Navigate to the file list
2. Click on a supported document file (docx, xlsx, pptx, etc.)
3. In the file preview modal, click "View Document"
4. The document opens in a full-screen viewer

#### Editing Documents

1. Navigate to the file list
2. Click the "Edit" button next to a supported document
3. The document opens in the ONLYOFFICE editor
4. Make your changes - autosave is enabled by default
5. Click "Close" when finished
6. Changes are automatically saved back to the original file location (local or S3)

#### Supported File Types

Editable formats:
- Word: docx, doc, odt, rtf, txt
- Excel: xlsx, xls, ods, csv
- PowerPoint: pptx, ppt, odp

View-only formats:
- PDF, DJVU, XPS

### Troubleshooting

- If documents fail to load, verify ONLYOFFICE can reach ProjectSend via the configured URL
- For S3 storage, files are proxied through ProjectSend to ONLYOFFICE
- Check browser console and `/tmp/onlyoffice_debug.log` for error details
- Ensure JWT secrets match between ProjectSend and ONLYOFFICE

## License

ProjectSend is licensed under [GNU GPL v2](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

## Change log

[Available at the official site](http://www.projectsend.org/change-log/)
