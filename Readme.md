# Essential Firewall Rules

## Inbound Rules

Port 80 (HTTP): Needs to go to your NAS's internal IP from your router
Port 443 (HTTPS): Needs to go to your NAS's internal IP from your router


Install Web Station:

Open DSM (Synology's operating system)
Go to Package Center
Search for and install "Web Station"

#  Making Your Website Public
## For external access, you'll need:

Domain Name Setup:

- Open DSM (Synology's operating system)
- Go to Control Panel
- Select External Access
- Click on the DDNS tab
- Click Add

You can use any service, personally I recommend no-ip.com, which you'll need to configure a domain and get the name/password for said domain. This will be different that login and password to access your account.


SSL Certificate (recommended):

In Control Panel → Security → Certificate Set up Let's Encrypt for free HTTPS. You will need to be able to access your personal web site from the domain name

First, connect to your NAS via SSH:

ssh admin@your-nas-ip

Create the directory where you want the content to appear in your web folder:

mkdir -p /volume1/web/plex

Use the mount --bind command to make the content available:
bashsudo mount --bind /volume1/Plex /volume1/web/plex

To make this mount persistent across reboots, edit the rc.local file:
sudo vi /etc/rc.local

Add the mount command before the exit 0 line:
sudo mount --bind /volume1/Plex /volume1/web/plex

Save and exit (press Esc, then type :wq and press Enter)