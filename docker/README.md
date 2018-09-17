# Create persistent data directory
Used for database, cache and application logs.
```
mkdir -p /var/lib/strichliste/
```

# Add host user
The container needs to be able to write to the persistent data directory.
Create a separate www-data user, if not available.
```
groupadd -g 82 www-data
useradd -r -s /usr/bin/nologin -u 82 -g 82 www-data
```

# Change permissions of data directory
```
chown -R www-data:www-data /var/lib/strichliste
```

# Run Docker
```
docker run -it -p 8080:8080 --mount type=bind,source=/var/lib/strichliste/,target=/source/var strichliste/server:v0.2.3
```