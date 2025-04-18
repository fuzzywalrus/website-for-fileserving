# NAS Web File Browser

Share files with your friends and family! This simple PHP application lets you create your own personal file-sharing website using your Synology NAS (or any web server with PHP support). Create a private file server that's easy to use and (fairly) secure. Run the HTTP server from a Raspberry Pi to metal gap your NAS.

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

1. Clone or download this repository to your web server
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


# HTTP Server configuration Guide

## Firewall Rules

On your router you will need to configure the following:

- Port 80 (HTTP): Forward to your http server's internal IP address
- Port 443 (HTTPS): Forward to yourhttp's internal IP address

## Domain Name Setup + Cloudflare (Highly recommended)
 	
I originally used (Cloudflare Tunnel Easy Setup)[https://www.crosstalksolutions.com/cloudflare-tunnel-easy-setup/] but I've since changed this to become more agnostic, the [Synology direct hosted](altconfigs.md) still exists. This guide shows how to set up a Cloudflared connector using Docker on a Raspberry Pi 4. While these instructions are specific to the Raspberry Pi 4, you can adapt them to other Linux distributions.

Cloudflare keeps you from exposing your IP and all of the security features 

Even at the free tier, Cloudflare offers substantial advantages for your Synology directory browser:

- IP Address Obfuscation: Cloudflare masks your home IP address, making it significantly harder for attackers to target your personal network directly.
- DDoS Protection: Your site gains protection against distributed denial of service attacks that could otherwise overwhelm your home internet connection.
- Web Application Firewall: Cloudflare automatically blocks common attack patterns and suspicious traffic before it reaches your server.
- SSL/TLS Encryption: Free, automatically renewed SSL certificates and encryption between visitors and Cloudflare improve security and privacy.
- Bot Protection: Basic protection against malicious bots that might attempt to scrape content or find vulnerabilities in your application.

## Prerequisites

* **Domain Name:** A registered domain (e.g., `mydomain.com`). *Tip:* Namecheap offers cost-effective domains.
* **Cloudflare Account:** Sign up at Cloudflare and add your domain.
* **Synology NAS with Docker:** Running Docker to host the `cloudflared` container.
* **Basic Networking Knowledge:** Familiarity with Docker, DNS, and Cloudflare's Zero Trust Dashboard.
* **Account on Synology with restricted access** Necessary if you are using a separate web server

## Configure Your Domain in Cloudflare

Sign up for cloudflare if you haven't already. You only need to use the free tier. Once the account has acreated you'll need to then sign into Zero Trust. See (Create a tunnel dashboard docs)[https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/get-started/create-remote-tunnel/] for more details.

* In Cloudflare Zero Trust Dashboard, select your tunnel and go to the **Networks > Tunnels** tab.
* Click **+ Cloudflared for the connector type**.

You should see suite of options. For ease of use, we are going to select docker.

## Setting Up Cloudflared Connector Using Docker

If doing this directly on your Synology NAS see [Synology direct hosted](altconfigs.md) although this is somewhat less secure, otherwise proceed here:


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

If your router supports VLAN, I recommend placing your web server computer in it's own VLAN and only giving it minimal access. This continues  defense in depth and principle of least privilege. If your http server gets pnwd, all that'd be exposed is the SMB share with read only privs. 


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