FROM php:8.2-apache

# 1. Dependencias del sistema + extensiones PHP
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mysqli gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. ELIMINAR físicamente mpm_event y mpm_worker, dejar SOLO mpm_prefork
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_worker.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 3. VALIDAR configuración en el build (si hay error, falla aquí, no en runtime)
RUN apache2ctl configtest

# 4. Límites PHP y zona horaria
RUN printf "upload_max_filesize=20M\npost_max_size=25M\nmemory_limit=256M\nmax_execution_time=120\ndate.timezone=America/Bogota\n" \
    > /usr/local/etc/php/conf.d/horion.ini

# 5. Copiar proyecto
COPY . /var/www/html/

# 6. Uploads con permisos
RUN mkdir -p /var/www/html/horion-time/public/uploads/marcaciones \
             /var/www/html/horion-time/public/uploads/novedades \
             /var/www/html/horion-time/public/uploads/horas_extras \
             /var/www/html/horion-time/public/uploads/empresas \
    && chown -R www-data:www-data /var/www/html/horion-time/public/uploads \
    && chmod -R 775 /var/www/html/horion-time/public/uploads

EXPOSE 80
CMD ["apache2-foreground"]
