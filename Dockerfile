FROM php:8.5-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev unzip git \
    && docker-php-ext-install pdo pdo_mysql

# Set working directory
WORKDIR /app

# Copy project
COPY . .

# Expose port
EXPOSE 8080

# Start PHP server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]