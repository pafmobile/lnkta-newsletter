=== LNKTA Newsletter Plugin ===
Contributors: lnkta
Requires at least: 5.8
Requires PHP: 7.2
Stable tag: 2.5.0

Preview/export existing newsletter posts and create Brevo campaign drafts.
No post types or ACF fields are registered or changed.

INSTALL / UPGRADE
1. Back up your site. Deactivate the old Petfeelings Newsletter Exporter first.
2. Install this ZIP under Plugins > Add New > Upload Plugin, then activate.
   The folder/name has changed, so WordPress treats this as a separate plugin.
   Do not run the old and new plugin together.
3. Existing newsletters, ACF fields, relationships and colors remain in place.

BREVO SETUP
1. Settings > LNKTA Newsletter: enter a Brevo API v3 key and save.
2. Select your subscriber list, enter the verified sender name/email and save.
3. Optional: set Reply-To, newsletter display brand, and postal/business identity.
   The email display brand defaults to your WordPress site name.
4. Authenticate your sender domain in Brevo. Keep existing mailbox DNS records.
5. Save/update a newsletter, edit the subject in its sidebar, then click
   Create Brevo draft. Only saved content is exported.
6. Open Brevo campaigns and locate the campaign ID reported by the plugin.
   Preview, send a test and send/schedule in Brevo yourself.

The plugin NEVER sends or schedules campaigns. The configurable footer includes {{ unsubscribe }} in downloaded HTML and
Brevo drafts only. Brevo replaces it with a personal unsubscribe URL when sending.
The WordPress preview displays non-clickable unsubscribe text instead.
Only administrators with manage_options and edit permission can create drafts.
Each successful click creates a NEW Brevo draft using the current saved
newsletter content. Retries of the same request reuse its result. Save changes
before clicking. Older campaigns are left unchanged. The campaigns link opens the Brevo campaign
listing; find your campaign by its ID/name.

API key: stored in a non-autoloaded WordPress option, not encrypted at rest.
The settings page never echoes the saved key. For configuration outside the
WordPress database, define LNKTA_BREVO_API_KEY in wp-config.php. This overrides
any saved key. Protect database backups and wp-config.php.

NETWORK ERRORS
A request with an uncertain result remains pending under its request identifier.
Check Brevo before creating another draft. Refreshing the editor starts a new
intentional request. A short lock prevents simultaneous campaign creation and
expires after 120 seconds if a request terminates unexpectedly.

STYLING
Email HTML and inline CSS live in newsletter_html() and article_html() in
lnkta-newsletter-plugin.php. Existing ACF foreground/background colors work.
Legacy export action and meta-box IDs are retained for compatibility.

2.2.0: LNKTA plugin branding, configurable email brand, Brevo settings/list
selection, subject editor, draft creation, unsubscribe footer and duplicate guard.

2.3.0 DESIGN SETTINGS
Settings > LNKTA Newsletter now includes:
- Logo upload (sanitized SVG, PNG or JPEG, up to 1 MB).
- Optional PNG email logo, used in all HTML when configured. Some email clients
  do not display SVG. Upload a PNG version for more consistent email display.
- Header and footer background/text colors, independently configurable.
  Blank values retain the existing per-newsletter ACF colors.
- Unsubscribe notice and link text. Use {unsubscribe} in the notice to position
  the link; if missing the link is appended. HTML in this notice is not allowed.

The header contains the logo on the left and newsletter number/year on the
right. Date fields remain saved but are no longer displayed.
The existing WordPress post_content editor supplies the introduction immediately
below the header. A new footer editor supplies newsletter_footer_content after
the posts and optional podcast. If an ACF field of that exact name already
exists, it is reused and the additional editor is hidden. No ACF fields or
post types are registered. Empty podcast selections produce no block or heading.
The address and unsubscribe notice appear inside the colored footer.

SVG handling is scoped to the administrator settings form. The PHP DOM extension
is required. Scripts, entities, external references, HTML, animation and CSS
style blocks are not supported. Export logos as paths with inline paint styles
for best results. Uploads are saved to the media library; removing/replacing the
logo setting does not delete the original attachment. Neither existing Brevo
drafts nor already sent campaigns are changed by this update.

2.3.1: Closing newsletter content appears in the main body after the articles
and optional podcast, before the colored footer. The footer contains only the
website, address and unsubscribe notice. Existing content is preserved.

2.3.2: Prevent relative unsubscribe URLs in WordPress previews. The Brevo
placeholder is included only in campaign HTML and downloaded HTML intended for
Brevo. Public previews cannot provide a personal unsubscribe URL without recipient
context; recipients should use the unsubscribe link in the email sent by Brevo.

2.4.0: Settings > LNKTA Newsletter > Links e destaques: cor adds a persistent
accent color picker for article read-more links and podcast accents.
Featured images prefer existing JPEG/PNG/GIF files, including original images
retained by optimization plugins. AVIF/WebP-only media is converted to cached
JPEG files in uploads/lnkta-newsletters when the host image editor supports it.
Original attachments are unchanged. If conversion is unsupported, a verified
existing source is retained. Missing derived images fall back to existing source
files; no remote URLs are downloaded. No new media attachment posts are created.
Images always use direct media URLs in src. Brevo click tracking URLs belong on
article hyperlinks only, not in image src attributes. Existing Brevo drafts must
be edited or have their content replaced in Brevo to receive these changes.

2.4.1: Add Testar ligação Brevo in Settings > LNKTA Newsletter. The test reads
contact lists only, using the saved key, and records its last result without the
key. HTTP errors now retain Brevo's sanitized message (including rejected server
IP if Brevo provides it), instead of calling all HTTP 401 responses invalid keys.
Network/SSL errors are shown separately. A successful response with zero lists
is distinguished from authentication failure. Save a changed key before testing.

2.5.0: Repeated draft creation from the same newsletter, with per-request retry
protection, last campaign ID and a button that remains available. Existing drafts
are untouched. Includes verified GitHub updates matching LNKTA Security Guard.

GITHUB UPDATES
Tools > LNKTA Newsletter Updates contains Check GitHub now and an opt-in
Automatically install verified releases checkbox. Default repository:
pafmobile/lnkta-newsletter. Public Ed25519 key is the same trusted public key
as LNKTA Security Guard. No API token or private signing key is stored in WordPress.
The repository must exist, be public, and publish signed releases before updates
work. A ZIP generated here is not itself a published GitHub release. The first
updater-enabled build must be installed manually. WordPress scheduled tasks,
PHP sodium and writable plugin files are required for automatic installation.
