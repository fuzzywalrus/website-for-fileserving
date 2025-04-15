# Synology (Or NAS) Web File Browser

Share files with your friends and family! This simple PHP application lets you create your own personal file-sharing website using your Synology NAS (or any web server with PHP support). Create a private file server that's easy to use and secure.

![screenshot](https://i.imgur.com/SPCMAOc.png)

## Features

- ✨ Clean, responsive Bootstrap UI
- 🔍 Built-in file name search
- 📄 HTML document preview
- 🔒 Simple password protection
- 🛡️ Secure file URLs (obfuscated as `file-handler.php?id=88bed9cf31e56c7fe7f165&download=1`)
- 🎬 Preview media files directly in browser
- 🔄 Easy to customize

## Installation

1. Clone or download this repository to your NAS's web directory
2. Create a `.env` file in the root directory (see Configuration section below)
3. Ensure your web server has PHP enabled
4. Access your site through your domain or IP address

## Configuration

Create a `.env` file in the root directory with the following content:

```
# Directory browser configuration
BASE_DIR=./directory-to-share
PASSWORD=your_secure_password
ENCRYPTION_KEY=use_a_strong_random_32_character_key

# Site customization
SITE_TITLE=My Directory Browser
SITE_STATS=Custom message for the footer goes here!

# Files and directories to exclude from listing
# Comma-separated list
EXCLUDED_ITEMS=@eaDir,#recycle,.DS_Store,Desktop DB,Desktop DF

# Environment (development/production)
ENVIRONMENT=production
```

### Important Notes:
- Replace `your_secure_password` with a strong password
- Generate a strong `ENCRYPTION_KEY` (you can use `openssl rand -hex 16` in terminal)
- Set `BASE_DIR` to the directory you want to share
- Customize `SITE_TITLE` and `SITE_STATS` to personalize your site

## Synology NAS Setup Guide

### 1. Install Web Station

1. Open DSM (Synology's operating system)
2. Go to Package Center
3. Search for and install "Web Station"

### 2. Enable External Access

#### Firewall Rules

On your router you will need to configure the following:

- Port 80 (HTTP): Forward to your NAS's internal IP address
- Port 443 (HTTPS): Forward to your NAS's internal IP address

#### Domain Name Setup

1. Open DSM
2. Go to Control Panel
3. Select External Access
4. Click on the DDNS tab
5. Click Add

You can use any DDNS service. [No-IP](https://www.noip.com/) is recommended for its simplicity. Note that the domain credentials will be different from your No-IP account login.

#### SSL Certificate (Recommended)

1. In Control Panel → Security → Certificate 
2. Set up Let's Encrypt for free HTTPS
3. You'll need to be able to access your website from your domain name

### 3. Link Your Content Directory

Connect to your NAS via SSH:

```bash
ssh admin@your-nas-ip
```

Create the directory for the web folder:

```bash
mkdir -p /volume1/web/directoryname
```

Use the `mount --bind` command to make your content available:

```bash
sudo mount --bind /volume1/path/to/your/files /volume1/web/directoryname
```

Make this mount persistent across reboots by editing the rc.local file:

```bash
sudo vi /etc/rc.local
```

Add this command before the `exit 0` line:

```bash
sudo mount --bind /volume1/path/to/your/files /volume1/web/directoryname
```

Save and exit (press Esc, then type `:wq` and press Enter)

## Security Considerations

- Always use HTTPS with a valid SSL certificate
- Choose a strong password and encryption key
- Regularly update your PHP version
- Be careful about what content you share
- Consider setting up a reverse proxy for added security

## Troubleshooting

- If files don't display, check file permissions
- Ensure PHP has read access to your content directory
- Check web server logs for any errors
- Verify that the .env file is properly formatted
- Make sure the `mount --bind` command was successful

## License

This project is available under the MIT License. See the LICENSE file for details.