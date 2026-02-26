These are the files for my current Website on arnovoyer.com. If there are any problems about this please contact me.

## Contact form setup (without SpaceMail)

The contact form currently uses your hosting mail server via PHP `mail()` in `contact.php`.
You do **not** need SpaceMail for this.

Optional server environment variables:

- `CONTACT_TO` = destination address (for example your Gmail/Outlook)
- `CONTACT_FROM` = sender address on your domain (for example `noreply@arnovoyer.com`)
- `CONTACT_FROM_NAME` = sender display name (optional)

Important:

- Hosting must allow outgoing mail from PHP.
- Set SPF and DKIM DNS records in Spaceship for reliable delivery.

## Emergency fallback (when hosting mail is blocked)

The form in `index.html` is configured to use FormSubmit directly:

- Endpoint: `https://formsubmit.co/ajax/arno.voyer@aon.at`
- No backend/API key needed
- First submission triggers an activation email from FormSubmit; confirm it once

After activation, form messages are delivered to the target mailbox.
