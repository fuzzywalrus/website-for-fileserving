# A database-free Web File Browser, turn any computer or NAS into a file server!

Share files with your friends and family! This simple PHP application lets you create your own personal file-sharing website using your NAS (or any web server with PHP support). Create a private file server that's easy to use and (fairly) secure. Run the HTTP server from a Raspberry Pi to metal gap your NAS. THis is a database free setup that uses a `.env` file for configuration which removes the headache of needing to set up a database and protecting it.

This allows Synology-like File Station on any NAS, and allows for more customized experience and less exposed solution. Host your HTTP server on a separate computer or virtual machine or however you like. 

![screenshot](https://i.imgur.com/SPCMAOc.png)

I wrote this so I could create a private web server to share files with a few friends and family members. If you have fast internet then you're certainly capable of hosting your own accessible-over-http file server.

## Features

- Clean, responsive Bootstrap UI
- Built-in file name search
- HTML document preview
- **HLS Video Streaming** (NEW!)
  - FFmpeg-powered video transcoding to 720p/1080p/480p
  - Hardware acceleration (Intel QuickSync, NVIDIA NVENC, AMD VCE)
  - Smart playback detection (direct for MP4/WebM, HLS for MKV/AVI)
  - Intelligent caching with 24-hour resume window
  - Video.js player with hls.js fallback for all browsers
  - Completely optional (can be disabled via .env)
- **Authentication System**
  - Password protection with brute force protection
  - Progressive rate limiting (30s → 2min → 5min → 15min → 1hr → 24hr)
  - "Remember Me" functionality with 30-day persistent login
  - Session timeout protection and IP validation
  - CSRF protection and secure session management
- **Modern Security**
  - File URLs protected with AES-256-GCM encryption + session binding
  - CSRF protection for file downloads with TTL tokens
  - Secure encrypted cookies for persistent authentication
  - Path traversal protection and comprehensive input validation
  - Multi-layer security logging and monitoring
- review media files directly in browser
-  **Flexible Session Management**
  - 24-hour sessions for regular logins
  - 30-day extended sessions with "Remember Me"
  - Automatic session cleanup and token management
- Easy to customize
- Instructions for CloudFlare Tunnel for reverse proxy protection
- Apache and NGNIX agnostic, use your preferred hosting
- Minimal configuration required via `.env` file
- Only Bootstrap JS used for client-side interactions (no frameworks)

## Installation

1. Clone or download this repository to your web server
2. Create a `.env` file in the root directory (see Configuration section below)
3. Ensure your web server has PHP enabled
4. **(Optional)** Install FFmpeg for video streaming (see Streaming Setup below)
5. **Secure your `.env` file** (see Web Server Security section below)
6. Access your site through your domain or IP address

### Web Server Security

**CRITICAL**: Protect your `.env` file from web access to prevent exposure of passwords and encryption keys.

**For Apache servers**, create or add to `.htaccess`:
```apache
<Files ".env">
  Require all denied
</Files>
```

**For Nginx servers**, add to your site configuration:
```nginx
location ~ /\.env {
  deny all;
}
```

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

For the AI assisted coders out there, AI agents shouldn't have much of a problem hacking in different accounts. I'd suggest asking the agent to reference this Readme.md for the `.env` file format, and then ask it to create account classes or tertiary accounts with different permissions.

### Secondary Access

Since I imagine many people want a secondary account class, you can specify another password and directory.

### HLS Video Streaming Configuration

The application now supports HLS video streaming with FFmpeg transcoding. Add these settings to your `.env` file:

```
# Enable/disable video streaming (when false, NO "View" button for videos)
ENABLE_STREAMING=true

# Video quality: 480p, 720p, or 1080p
STREAMING_QUALITY=720p

# Cache directory for transcoded videos
STREAMING_CACHE_DIR=./cache/hls

# Cache retention (seconds since last access - default 24 hours)
# Videos can be paused/resumed within this window
STREAMING_CACHE_TTL=86400

# Hardware acceleration: auto, intel, nvidia, amd, or none
HW_ACCEL=auto

# FFmpeg paths (usually auto-detected)
FFMPEG_PATH=/usr/bin/ffmpeg
FFPROBE_PATH=/usr/bin/ffprobe
```

**Important Notes:**
- When `ENABLE_STREAMING=false`, video files will have **NO** "View" button (download only)
- When `ENABLE_STREAMING=true`, all video files get a "View" button with smart playback
- MP4/WebM files play directly in the browser (no transcoding needed)
- MKV/AVI/MOV files are transcoded to HLS on first view, then cached
- Cache uses last access time, not creation time - videos stay cached while actively watched 

### Important Notes:
- Replace `your_secure_password` with a strong password
- **Generate a strong `ENCRYPTION_KEY`**: Use `openssl rand -hex 32` to create a secure 64-character hex string
  - This key is used for AES-256-GCM encryption of file URLs for enhanced security
  - Example: `openssl rand -hex 32` generates something like: `a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456`
- Set `BASE_DIR` to the directory you want to share
- Customize `SITE_TITLE` and `SITE_STATS` to personalize your site

## HLS Video Streaming Setup

### Overview

The HLS (HTTP Live Streaming) feature allows you to stream video files directly in the browser with automatic transcoding to a consistent quality (480p/720p/1080p). Videos are transcoded once and cached for quick resume within a 24-hour window.

### Dependencies

**Required for streaming:**
- FFmpeg with H.264 encoding support
- PHP with `exec()` function enabled
- Sufficient disk space for cache (temporary transcoded videos)

**Optional but recommended:**
- Hardware acceleration support (Intel QuickSync, NVIDIA NVENC, or AMD VCE)
- VA-API drivers (for Intel/AMD)
- CUDA toolkit (for NVIDIA)

### Quick Setup

1. **Run the automated setup script:**
   ```bash
   cd /path/to/website
   ./setup-ffmpeg.sh
   ```
   
   This script will:
   - Install FFmpeg and required tools
   - Detect available hardware acceleration
   - Install appropriate drivers
   - Test encoding capability
   - Provide recommended `.env` settings

2. **Create/update your `.env` file:**
   ```bash
   cp .env.example .env
   nano .env
   ```
   
   Set `ENABLE_STREAMING=true` and configure other streaming options.

3. **Log out and log back in** (required for group membership changes to take effect)

4. **Test the setup:**
   ```bash
   ./start-server.sh
   ```
   
   Access the test server at `http://localhost:8080` and try viewing a video file.

### Hardware Acceleration

Hardware acceleration significantly reduces CPU usage and speeds up transcoding.

**Supported platforms:**

| Platform | Technology | Encoder | Performance |
|----------|-----------|---------|-------------|
| Intel | QuickSync (VA-API) | `h264_vaapi` | ⚡⚡⚡ Excellent |
| NVIDIA | NVENC | `h264_nvenc` | ⚡⚡⚡ Excellent |
| AMD | VCE (VA-API) | `h264_vaapi` | ⚡⚡ Good |
| Software | libx264 | `libx264` | ⚡ Slow |

**Auto-detection:**

Set `HW_ACCEL=auto` in your `.env` file (recommended). The system will automatically detect and use the best available hardware acceleration.

**Manual selection:**

If auto-detection fails, you can manually specify:
- `HW_ACCEL=intel` - Force Intel QuickSync
- `HW_ACCEL=nvidia` - Force NVIDIA NVENC
- `HW_ACCEL=amd` - Force AMD VCE
- `HW_ACCEL=none` - Use software encoding

**Testing hardware acceleration:**

```bash
# Test Intel QuickSync
ffmpeg -hwaccel vaapi -hwaccel_device /dev/dri/renderD128 -i test.mkv -c:v h264_vaapi -f null -

# Test NVIDIA NVENC
ffmpeg -hwaccel cuda -i test.mkv -c:v h264_nvenc -f null -

# Check VA-API info
vainfo
```

### Cache Management

The caching system intelligently manages transcoded videos:

**How it works:**
1. Video is transcoded to HLS on first view
2. Segments are cached in `STREAMING_CACHE_DIR`
3. Last access time is updated each time video is played
4. Cache entries are kept for `STREAMING_CACHE_TTL` seconds since last access
5. Unwatched videos are automatically cleaned up after TTL expires

**Benefits:**
- Watch videos immediately after first transcode
- Pause and resume videos within 24-hour window
- Rewatch videos without re-transcoding
- Automatic cleanup prevents disk space issues
- No manual cache management needed

**Manual cache cleanup:**

```bash
# Dry run (see what would be deleted)
php cleanup-cache.php --dry-run --verbose

# Actually clean up (removes expired entries based on TTL)
php cleanup-cache.php --verbose

# Force clear ALL cache (delete everything)
rm -rf ./cache/hls/*

# Add to cron (daily cleanup at 3 AM)
crontab -e
# Add this line:
0 3 * * * cd /path/to/website && php cleanup-cache.php >> /var/log/hls-cache-cleanup.log 2>&1
```

**Cache statistics:**

The cleanup script shows:
- Number of directories cleaned
- Space freed
- Remaining cache size
- List of cached videos (with `--verbose`)

### Video Quality Settings

Choose the quality that fits your needs:

| Quality | Resolution | Bitrate | Use Case |
|---------|-----------|---------|----------|
| 480p | 854x480 | ~2 Mbps | Mobile devices, slower connections |
| 720p | 1280x720 | ~2 Mbps | Default, good balance |
| 1080p | 1920x1080 | ~2 Mbps | High quality, requires more bandwidth |

Set `STREAMING_QUALITY` in your `.env` file. Higher quality means:
- ✅ Better video quality
- ❌ Larger cache files
- ❌ More CPU/GPU usage
- ❌ More bandwidth required

### Supported Video Formats

**Browser-native formats (direct playback, no transcoding):**
- MP4 (H.264)
- WebM
- OGG

**Formats requiring HLS transcoding:**
- MKV (Matroska)
- AVI
- MOV (QuickTime)
- WMV (Windows Media)
- FLV (Flash)
- M4V
- MPG/MPEG
- And more...

### Troubleshooting Streaming

#### Videos don't have a "View" button

**Cause:** Streaming is disabled

**Solution:**
1. Check `.env` file: `ENABLE_STREAMING=true`
2. Restart web server
3. Clear browser cache

#### "Video streaming is not enabled" error

**Cause:** Streaming configuration not loaded

**Solution:**
1. Verify `.env` file exists
2. Check `ENABLE_STREAMING=true` is set
3. Verify no syntax errors in `.env`
4. Check web server logs for configuration errors

#### "FFmpeg not found" error

**Cause:** FFmpeg not installed or wrong path

**Solution:**
```bash
# Install FFmpeg
sudo apt install ffmpeg

# Find FFmpeg path
which ffmpeg

# Update .env with correct path
FFMPEG_PATH=/usr/bin/ffmpeg
```

#### Video transcoding is very slow

**Cause:** Using software encoding (no hardware acceleration)

**Solution:**
1. Run `./setup-ffmpeg.sh` to check hardware support
2. Install appropriate drivers:
   ```bash
   # Intel
   sudo apt install intel-media-va-driver i965-va-driver
   
   # AMD
   sudo apt install mesa-va-drivers
   
   # NVIDIA
   # Install CUDA toolkit from nvidia.com
   ```
3. Set `HW_ACCEL=auto` in `.env`
4. Restart web server

#### "Transcoding failed" error

**Possible causes:**
- Corrupted video file
- Unsupported codec
- Insufficient disk space
- Permission issues

**Solution:**
```bash
# Check disk space
df -h

# Check cache directory permissions
ls -ld ./cache/hls
chmod 755 ./cache/hls

# Test FFmpeg manually
ffmpeg -i /path/to/video.mkv -c:v libx264 -c:a aac test.mp4

# Check web server error logs
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/php_errors.log
```

#### Videos buffer frequently during playback

**Possible causes:**
- Slow server CPU
- Network congestion
- Quality setting too high for connection speed

**Solution:**
1. Lower quality setting: `STREAMING_QUALITY=480p`
2. Enable hardware acceleration
3. Check server CPU usage during playback
4. Consider upgrading server hardware

#### Cache fills up disk

**Cause:** Long TTL or many large videos

**Solution:**
1. Reduce cache TTL: `STREAMING_CACHE_TTL=43200` (12 hours)
2. Run cleanup script manually: `php cleanup-cache.php`
3. Add cron job for automatic cleanup
4. Monitor cache size: `du -sh ./cache/hls`

#### "exec() has been disabled" error

**Cause:** PHP `exec()` function disabled for security

**Solution:**
Edit `php.ini` and remove `exec` from `disable_functions`:
```ini
; Before
disable_functions = exec,passthru,shell_exec,system

; After
disable_functions = passthru,shell_exec,system
```

Then restart web server.

#### Hardware acceleration not working

**Check device access:**
```bash
# Check if GPU device exists
ls -l /dev/dri/renderD128

# Check user permissions
groups $USER

# Add user to video group if needed
sudo usermod -a -G video $USER
sudo usermod -a -G render $USER

# Log out and log back in
```

**Verify VA-API:**
```bash
vainfo
# Should show driver information and supported profiles
```

**Verify NVIDIA:**
```bash
nvidia-smi
# Should show GPU information

nvcc --version
# Should show CUDA version
```

### Performance Tips

1. **Use hardware acceleration** - Reduces CPU usage by 80-90%
2. **Adjust quality for your needs** - 720p is usually sufficient
3. **Monitor cache size** - Set up automatic cleanup
4. **Use SSD for cache** - Faster read/write for HLS segments
5. **Allocate sufficient RAM** - FFmpeg needs memory for transcoding
6. **Test with different codecs** - Some videos transcode faster than others

### Development and Testing

**Start development server:**
```bash
./start-server.sh
# Or custom port:
./start-server.sh 8081
```

**Test with sample videos:**
1. Place test videos in your `BASE_DIR`
2. Access site at `http://localhost:8080`
3. Click "View" on a video file
4. Check transcoding in server logs

**Monitor transcoding:**
```bash
# Watch PHP error log
tail -f /var/log/php_errors.log

# Monitor cache directory
watch -n 1 "du -sh ./cache/hls && ls -lh ./cache/hls/*/*"
```

## Authentication & Security Features

### Enhanced Login System
The application now includes enterprise-grade authentication with multiple security layers:

**Brute Force Protection:**
- Progressive rate limiting with escalating timeouts
- Automatic IP-based lockouts after failed attempts
- Secure logging of all authentication events
- Protection against automated attack tools

**Remember Me Functionality:**
- Optional 30-day persistent login via encrypted cookies
- Secure token-based authentication
- Automatic re-authentication on return visits
- One-click logout clears all authentication data

**Session Security:**
- **Regular Sessions**: 24-hour automatic timeout
- **Extended Sessions**: 30-day timeout with "Remember Me"
- Session regeneration prevents fixation attacks
- IP validation detects potential session hijacking
- CSRF protection on all forms

### Security Implementation Details

**Rate Limiting Schedule:**
```
Attempt 1-4: Warning messages (allowed within 30 seconds)
Attempt 5: 30-second lockout
Attempt 6: 2-minute lockout
Attempt 7: 5-minute lockout
Attempt 8: 15-minute lockout
Attempt 9+: 1-hour to 24-hour lockout
```

**File Access Security:**
- Session-bound encrypted file tokens prevent CSRF attacks
- 24-hour TTL on file access tokens (allows large file downloads)
- Origin/Referer header validation blocks cross-site requests
- Multi-factor token validation (session + user + expiry)
- Anti-caching headers prevent shared proxy/CDN exposure

**Cookie Security:**
- All authentication cookies are encrypted using AES-256-GCM
- HttpOnly and Secure flags prevent client-side access
- SameSite=Strict prevents cross-site request forgery
- Automatic expiration and cleanup of old tokens

**Logging & Monitoring:**
- All login attempts logged with IP addresses and timestamps
- Failed authentication attempts tracked and reported
- Session security events recorded for audit trails
- Rate limit violations logged for security analysis
- CSRF attack attempts detected and logged
- File access token validation events tracked


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

**You'll need to mount your NAS volum**  into the `/var/www/html` or you will need to directly host files in a directory on your webserver.

Install the PHP from this  files into the `html` and create the .env file and be sure to set the mounted volume or the directory of your chosing as the BASE_DIR.


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

### Authentication Security
- **Strong Passwords**: Use complex passwords that are difficult to guess or brute force
- **Encryption Key**: Generate using `openssl rand -hex 32` for maximum AES-256 security
- **Remember Me Usage**: Only enable on trusted devices - extends session to 30 days
- **Regular Logout**: Manually logout on shared computers to clear all authentication data
- **Monitor Logs**: Check server logs for suspicious login patterns or rate limit violations

### System Security
- **File URL Protection**: File paths are encrypted using AES-256-GCM with session binding and TTL
- **CSRF Attack Prevention**: File downloads protected against cross-site request forgery
- **Cache Security**: Anti-caching headers prevent private files from being stored in shared proxies/CDNs
- **Session Management**: Extended sessions are automatically secured with encrypted persistent cookies
- **Multi-Layer CSRF Protection**: Forms, file access, and headers all include anti-forgery protection
- **IP Validation**: Optional session IP checking helps detect potential session hijacking
- **Automatic Cleanup**: Expired sessions, tokens, and file access credentials are automatically removed

### Infrastructure Security
- **Regular Updates**: Keep PHP version current to get the latest security patches
- **Content Awareness**: Only share files you're comfortable with authorized users accessing
- **Network Isolation**: Place HTTP server on separate VLAN for defense in depth
- **HTTPS**: Use SSL/TLS encryption (via Cloudflare) for all traffic to protect credentials
- **Log Monitoring**: Regular review of authentication logs helps identify security issues
- **Enterprise Safe**: Works securely behind corporate proxies without cache leakage
- **CDN Compatible**: Can be used with CDNs while maintaining file privacy

### Best Practices
- **Use "Remember Me" only on personal devices** - avoid on public computers
- **Regular password changes** if you suspect compromise
- **Monitor failed login attempts** in server logs
- **Clear browser data** when finished using shared computers
- **Keep encryption key secure** - treat it like a master password
- **Shared networks safe**: Files won't be cached in corporate proxies or CDNs
- **Private content stays private**: Anti-caching headers prevent unintended sharing 

## Troubleshooting

### General Issues
- If files don't display, check file permissions
- Ensure PHP has read access to your content directory
- Check web server logs for any errors
- Verify that the .env file is properly formatted
- Make sure the `mount --bind` command was successful

### Authentication Issues

**"Too many failed login attempts" Error:**
- You get 4 attempts within the first 30 seconds before lockout begins
- Wait for the specified time period before trying again
- Check server logs to see if your IP is being rate limited
- Ensure you're using the correct password
- Clear browser cookies and try again

**"Remember Me" Not Working:**
- Verify cookies are enabled in your browser
- Check that your encryption key is properly set in .env
- Ensure the server time is correct (affects cookie expiration)
- Try clearing browser cookies and logging in again

**Session Expired Messages:**
- Regular sessions expire after 24 hours
- Extended sessions (with "Remember Me") expire after 30 days
- Session may be invalidated if your IP address changes
- Manual logout clears all session data

**CSRF Token Errors:**
- Clear browser cookies and refresh the page
- Ensure JavaScript is enabled
- Check that the session hasn't expired
- Try logging out and back in

**Auto-login Not Working:**
- Verify the "Remember Me" checkbox was checked during login
- Check browser cookie settings allow persistent cookies
- Ensure the domain matches between login and access
- Server logs will show if remember me tokens are being validated

**File Download Issues:**
- **"Cross-origin request blocked"**: File links have 24-hour expiration for security
- **"Invalid file ID format"**: File access tokens may have expired, refresh the page
- **Downloads fail from bookmarks**: Old bookmarked file links expire after 24 hours
- **External links don't work**: File downloads are protected against cross-site access

**Token Expiration Messages:**
- File access tokens expire after 24 hours for security
- Refresh the file listing page to generate new download links after expiration
- 24-hour window accommodates large files and slow connections
- Direct file URLs are session-bound and user-specific

### Log Analysis
Check your web server error logs for detailed information:
```bash
# Apache logs
tail -f /var/log/apache2/error.log

# PHP logs
tail -f /var/log/php_errors.log

# Look for authentication events like:
# "Successful login from IP: x.x.x.x (User type: primary)"
# "Failed login attempt from IP: x.x.x.x (Attempt 3)"
# "Blocked login attempt from IP: x.x.x.x"

# Look for security events like:
# "File access token expired (created: 2024-01-01 12:00:00)"
# "Session ID mismatch in file access token"
# "Potential CSRF attempt from IP: x.x.x.x, Origin: https://evil.com"
# "Invalid input too long for field: password (length: 5000, max: 1000)"
```

## License

This project is available under the MIT License. See the LICENSE file for details.
