# Synology (Or NAS) Web File Browser

Share files with your friends and family! This simple PHP application lets you create your own personal file-sharing website using your Synology NAS (or any web server with PHP support). Create a private file server that's easy to use and (fairly) secure.

![screenshot](https://i.imgur.com/SPCMAOc.png)

I wrote this so I could create a private web server to share files with a few friends and family members. If you have FiOS then you're certainly capable of hosting your own accessible-over-http file server.

## Features

- ✨ Clean, responsive Bootstrap UI
- 🔍 Built-in file name search
- 📄 HTML document preview
- 🔒 Simple password protection
- 🛡️ Secure file URLs (obfuscated as `file-handler.php?id=88bed9cf31e56c7fe7f165&download=1`) + traversal protection, double encoding protection, recursively decoding URL Params, and sanitation for URL paths, and validates file names, and moved to HMCA SHA-256, and escaping. 
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

SECONDARY_PASSWORD=guest_password_here
SECONDARY_BASE_DIR=./limited_access_directory
SECONDARY_TITLE=Guest Access Portal

# Files and directories to exclude from listing
# Comma-separated list
EXCLUDED_ITEMS=@eaDir,#recycle,.DS_Store,Desktop DB,Desktop DF

# Environment (development/production)
ENVIRONMENT=production
```

### Secondary Access

Since I imagine many people want a secondary account class, you can specify another password and directory. 

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

# Cloudflare (Highly recommended)
 	
I highly recommend (Cloudflare Tunnel Easy Setup)[https://www.crosstalksolutions.com/cloudflare-tunnel-easy-setup/] or the youtube version, [Crosstalk solutions: You Need to Learn This! Cloudflare Tunnel Easy Tutorial](https://www.youtube.com/watch?v=ZvIdFs3M5ic). I've slightly updated it as the tunnels has moved into zero trust and now lives under updates.

Cloudflare keeps you from exposing your IP and all of the security features 

Even at the free tier, Cloudflare offers substantial advantages for your Synology directory browser:

- IP Address Obfuscation: Cloudflare masks your home IP address, making it significantly harder for attackers to target your personal network directly.
- DDoS Protection: Your site gains protection against distributed denial of service attacks that could otherwise overwhelm your home internet connection.
- Web Application Firewall: Cloudflare automatically blocks common attack patterns and suspicious traffic before it reaches your server.
- SSL/TLS Encryption: Free, automatically renewed SSL certificates and encryption between visitors and Cloudflare improve security and privacy.
- Bot Protection: Basic protection against malicious bots that might attempt to scrape content or find vulnerabilities in your application.

# Prerequisites

* **Domain Name:** A registered domain (e.g., `mydomain.com`). *Tip:* Namecheap offers cost-effective domains.
* **Cloudflare Account:** Sign up at Cloudflare and add your domain.
* **Synology NAS with Docker:** Running Docker to host the `cloudflared` container.
* **Basic Networking Knowledge:** Familiarity with Docker, DNS, and Cloudflare's Zero Trust Dashboard.
* **Account on Synology with restricted access** Necessary if you are using a separate web server

## 1. Configure Your Domain in Cloudflare

# Setting Up Cloudflared Connector Using Docker on Raspberry Pi 4

This guide shows how to set up a Cloudflared connector using Docker on a Raspberry Pi 4. While these instructions are specific to the Raspberry Pi 4, you can adapt them to other Linux distributions.

## 1. Download all the software for your server

```bash
sudo apt update
sudo apt install samba samba-common-bin apache2 php libapache2-mod-php -y
curl -sSL https://get.docker.com | sh
```

## 2. Create a backup of your smb.conf

```bash
sudo cp /etc/samba/smb.conf /etc/samba/smb.conf.backup
```

## 3. Edit the Samba configuration file

```bash
sudo nano /etc/samba/smb.conf
```

Then add to the end of the file:

```
[www]
   comment = Apache Web Directory
   path = /var/www
   browseable = yes
   writeable = yes
   create mask = 0664
   directory mask = 0775
   public = no
```

## 4. Setup a SMB password

This will let you connect to your server via SMB so you can deploy the files. Replace `username` with your user.

```bash
sudo smbpasswd -a username
```

## 5. Restart the Samba service and ensure the Apache user (www-data) and your user are in the same group with permissions

Again, replace `username` with your user.

```bash
sudo systemctl restart smbd

sudo usermod -a -G www-data username
sudo usermod -a -G username www-data
sudo chown -R username:www-data /var/www
sudo chmod -R 775 /var/www
```

## 6. Configure services to launch on reboot

```bash
# Check current status
sudo systemctl status apache2

# Enable Apache2 to start on boot
sudo systemctl enable apache2

# If it's not already running, start it now
sudo systemctl start apache2

# Check current status
sudo systemctl status docker

# Enable Docker to start on boot
sudo systemctl enable docker

# If it's not already running, start it now
sudo systemctl start docker

# Check if Apache2 is set to start on boot
sudo systemctl is-enabled apache2

# Check if Docker is set to start on boot
sudo systemctl is-enabled docker
```

## 7. Verify Apache is working properly

```bash
# Create a test page
echo "<html><body><h1>My Cloudflare Tunnel Test</h1></body></html>" | sudo tee /var/www/html/index.html

# Test locally
curl http://localhost
```

## 8. Configure the tunnel

You will need the token from the Cloudflare Zero Trust panel.

```bash
docker run -d --name cloudflared --restart always cloudflare/cloudflared:latest tunnel --no-autoupdate run --token [token goes here]
```

## 9. Check tunnel status

```bash
# View logs to verify connection
docker logs cloudflared
```

## 10. Configure the website!

The Zero Trust panel will tell you if your tunnel is connected correctly. At this point, you should be able to test your webserver from your domain.  

**You'll need to mount your NAS volum**  into the `/var/www/html`

Install the PHP from this  files into the `html` and create the .env file and be sure to set the mounted volume as the BASE_DIR.


## 11. Optional: Configure basic firewall

```bash
sudo apt install ufw -y
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow samba
sudo ufw enable
```


### Synology only route (easier but less sucure) 

1. **Download the Docker Image:**
   * Open the Synology Docker application.
   * Go to **Registry** and search for `cloudflare/cloudflared`; download the `latest` image.
2. **Create and Configure the Container:**
   * Launch the image to create a new container.
   * **Network Mode:** Use the same network as the Docker host.
   * **Container Name:** For example, `cloudflared-connector`.
   * **Auto-Restart:** Enable auto-restart.
   * **Command Configuration:**
      * Edit the execution command (under Advanced Settings) by pasting a modified command string. Remove extraneous parts so that it resembles:

```
tunnel run --token <YOUR_TOKEN>
```

## 4. Add LAN Services to Your Tunnel

1. **Define Public Hostnames:**
   * In Cloudflare Zero Trust Dashboard, select your tunnel and go to the **Public Hostname** tab.
   * Click **+ Add a public hostname**.
2. **Configure Each Service:**
   * **Synology NAS GUI:**
      * **Subdomain:** `nas` (resulting in `nas.yourdomain.com`)
      * **Service Type:** HTTP
      * **URL:** `http://192.168.x.x:5000`
   * **PiHole:**
      * **Subdomain:** `pihole`
      * **Service Type:** HTTP
      * **URL:** `http://192.168.x.x/admin`
   * **Firewall (EdgeRouter):**
      * **Subdomain:** `firewall`
      * **Service Type:** HTTPS *Note:* If you encounter certificate errors, open **Additional Application Settings** and enable **No TLS Verify**.
3. **Save Each Hostname:**
   * Once saved, accessing these FQDNs from the internet will route you securely to your internal resources via HTTPS (with Cloudflare's certificate).

## 5. Secure Tunnel Access with Cloudflare Zero Trust

1. **Set Up an Authentication Method:**
   * Navigate to **Settings** > **Authentication** in the Cloudflare Zero Trust Dashboard.
   * Add the **One-time PIN** login method (or use your preferred SSO).
2. **Create an Application and Access Policy:**
   * Go to **Applications** > **Add an application** > **Self-hosted**.
   * Use a wildcard (`*`) for the subdomain to protect any FQDN in your domain.
   * **Identity Providers:** Select One-time PIN (or your desired method).
   * Under **Create additional rules**, add selectors (e.g., specific email addresses or an email domain like `@yourdomain.com`).
   * Save your policy and application.

## 6. Test Your Setup

1. **Access Your Service:**
   * Open a browser and go to one of your defined FQDNs (e.g., https://nas.yourdomain.com).
2. **Authenticate:**
   * Enter your email address to receive a one-time PIN, then input the PIN to gain access.
3. **Verify:**
   * Ensure the resource loads securely, and that non-authorized access is blocked.


## Not very secure way

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

This is optional but if you want to link to files hosted elsewhere on your NAS you'll need to bind the directory as symbolic links will not work.

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