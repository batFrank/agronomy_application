FROM debian:12-slim

RUN apt-get update && apt-get install -y \
      bash

# Install Apache
RUN apt-get update && apt-get install -y \
      apache2 \
      ssl-cert \
      vim \
      wget \
      openssl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod ssl headers rewrite proxy proxy_http proxy_fcgi

# Create a configuration file for OpenSSL with SANs
RUN echo "[req]" > /tmp/openssl.cnf && \
    echo "distinguished_name=req_distinguished_name" >> /tmp/openssl.cnf && \
    echo "[req_distinguished_name]" >> /tmp/openssl.cnf && \
    echo "[v3_req]" >> /tmp/openssl.cnf && \
    echo "subjectAltName=DNS:localhost,DNS:md-backend.local" >> /tmp/openssl.cnf \
    && make-ssl-cert generate-default-snakeoil --force-overwrite \
    && openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/ssl/private/apache-selfsigned.key \
        -out /etc/ssl/private/apache-selfsigned.crt \
        -subj "/C=US/ST=Denial/L=Springfield/O=Dis/CN=localhost" \
        -extensions v3_req -config /tmp/openssl.cnf \
    && rm /tmp/openssl.cnf \
    && chown -R www-data:www-data /etc/ssl/private/apache-selfsigned.key /etc/ssl/private/apache-selfsigned.crt \
    && chmod 600 /etc/ssl/private/apache-selfsigned.key /etc/ssl/private/apache-selfsigned.crt \
    && a2ensite default-ssl

# Start Apache - using apache2ctl to load envvars properly
#CMD ["apache2ctl", "-D", "FOREGROUND"]
ARG UID=1000
ARG GID=1000

# Sincronizamos www-data con tu usuario del host
RUN groupmod -g ${GID} www-data && \
    usermod -u ${UID} -g www-data www-data

# Variables necesarias para que Apache arranque correctamente
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOG_DIR /var/log/apache2
ENV APACHE_PID_FILE /var/run/apache2/apache2.pid
ENV APACHE_RUN_DIR /var/run/apache2
ENV APACHE_LOCK_DIR /var/lock/apache2

RUN mkdir -p $APACHE_RUN_DIR $APACHE_LOCK_DIR $APACHE_LOG_DIR

# Usamos exec y el binario directo para que cierre rápido
CMD ["/bin/bash", "-c", "source /etc/apache2/envvars && exec /usr/sbin/apache2 -D FOREGROUND"]
