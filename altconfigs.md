## Synology NAS Setup Guide

I highly recommend (Cloudflare Tunnel Easy Setup)[https://www.crosstalksolutions.com/cloudflare-tunnel-easy-setup/] or the youtube version, [Crosstalk solutions: You Need to Learn This! Cloudflare Tunnel Easy Tutorial](https://www.youtube.com/watch?v=ZvIdFs3M5ic). I've slightly updated it as the tunnels has moved into zero trust and now lives under updates.

### 1. Install Web Station

1. Open DSM (Synology's operating system)
2. Go to Package Center
3. Search for and install "Web Station"


## 2. Synology Add LAN Services to Your Tunnel

This is less recommended, but you can run your web server directly from a Synology NAS

1. **Define Public Hostnames:**
   * In Cloudflare Zero Trust Dashboard, select your tunnel and go to the **Networks > Tunnels** tab.
   * Click **+ Add a public hostname**.
2. **Configure Each Service:**
   * **Synology NAS GUI:**
      * **Subdomain:** `nas` (resulting in `nas.yourdomain.com`)
      * **Service Type:** HTTP
      * **URL:** `http://192.168.x.x:5000`
   * **Firewall (EdgeRouter):**
      * **Subdomain:** `firewall`
      * **Service Type:** HTTPS *Note:* If you encounter certificate errors, open **Additional Application Settings** and enable **No TLS Verify**.
3. **Save Each Hostname:**
   * Once saved, accessing these FQDNs from the internet will route you securely to your internal resources via HTTPS (with Cloudflare's certificate).

## 3. Secure Tunnel Access with Cloudflare Zero Trust

1. **Set Up an Authentication Method:**
   * Navigate to **Settings** > **Authentication** in the Cloudflare Zero Trust Dashboard.
   * Add the **One-time PIN** login method (or use your preferred SSO).
2. **Create an Application and Access Policy:**
   * Go to **Applications** > **Add an application** > **Self-hosted**.
   * Use a wildcard (`*`) for the subdomain to protect any FQDN in your domain.
   * **Identity Providers:** Select One-time PIN (or your desired method).
   * Under **Create additional rules**, add selectors (e.g., specific email addresses or an email domain like `@yourdomain.com`).
   * Save your policy and application.

## 4. Test Your Setup

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