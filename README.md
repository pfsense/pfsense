OAuth authentication via Google
===============================
Here are the steps to perform in order to configure the service.
 * Log in on your google account and go to https://cloud.google.com/console.
   Create a project, then under its APIs & auth/Credentials menu, create a new client ID.
   * keep 'Web application' for Application type
   * put your callback URL(s) in *Authorized redirect URI*(__domain.com__ for example). *Attention*: this URL must be public(not local like __domain.lan__), otherwise Google will refuse the redirection. Set the same URL in *HTTPS server name*.

   Once the project created, some parameters will be generated. We need *Client ID* and *Client secret*.

 * Go to pfSense administration interface, activate the *OAuth2(via Google)* option(in *Authentication* section) for the zones you need to. Set the ID and the Secret and save the configuration.
 * In order to let Google's authentication page display properly, the IP for *accounts.google.com*, *ssl.gstatic.com* and probably the local(depending on your country) google's accounts page(like *accounts.google.fr*) should be opened on captive portal.
 * Enable the *DNS forwarder* on the captive portal's interface and add previous URL domain with its IP in *Host Overrides*. Optionnaly you could also add Google's domains
