FROM php:8.2-apache

# 1. Instalar dependencias del sistema para GD y otras extensiones
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libz-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        mbstring \
        exif \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2. FIX: Deshabilitar todos los MPMs y habilitar solo prefork (compatible con PHP)
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
 && a2enmod mpm_prefork rewrite \
 && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 3. Configuración PHP optimizada para HORION TIME
RUN echo "upload_max_filesize=20M\npost_max_size=25M\nmemory_limit=256M\nmax_execution_time=120\ndate.timezone=America/Bogota" \
    > /usr/local/etc/php/conf.d/horion.ini

# 4. Copiar todo el proyecto
COPY . /var/www/html/

# 5. Crear carpetas de uploads con permisos
RUN mkdir -p /var/www/html/public/uploads/marcaciones \
             /var/www/html/public/uploads/novedades \
             /var/www/html/public/uploads/horas_extras \
             /var/www/html/public/uploads/empresas \
 && chown -R www-data:www-data /var/www/html/public/uploads \
 && chmod -R 775 /var/www/html/public/uploads

# 6. Configurar Apache para servir desde /public/
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80
CMD ["apache2-foreground"]