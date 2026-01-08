This is a quick html and php file to test LDAP.
A quick way to deploy the webserver with apache and nginx (used a Debian 13) in one command:

apt update && apt upgrade -y && \
apt install -y apache2 php php-ldap php-cli libapache2-mod-php ldap-utils && \
mkdir -p /var/www/registration /var/log/ldap-test && \
chown -R www-data:www-data /var/www/registration /var/log/ldap-test && \
chmod 755 /var/www/registration /var/log/ldap-test && \
a2enmod rewrite && a2enmod ssl && \
systemctl start apache2 && systemctl enable apache2 && \

Also make sure you change the permission to 644 for both files (chmod 644 ldap-test.php && chmod644 ldap-test.html)

Hope this helps!
