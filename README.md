## Dev mode

### 1st time
```bash
make frontend-install
```

### During work
```bash
make dev
```
Then just edit scss files, watcher will automatically compile them to uncompressed style.css and will provide a style.css.map for helping to debug.


### Once ready to deliver
```bash
make frontend
```
This will build the definitive and compressed style.css with no map

## Deploy mode and prod
```bash
make
```

## General informations

### Frontend
- UI
- Login / register
- Galerie
- Upload, webcam, filters

autorisé : routes HTTP, JSON, sessions/JWT, hash mot de passe, upload, accès SQL, mails

### Services

#### Auth-service
- Register
- Login
- Session
- Validation

#### Media-service
- Upload
- Picture edit
- Filters
- Files

#### post-service
- Gallery
- Posts
- Likes

#### notification-service
- Emails
- Alert


## Bonuses
- Notifications modification
- live edit picture from cam
- Theme White/Dark
- User account delete and data anonymization
- Effects photos (Noir et blanc, old TV..., filters)
