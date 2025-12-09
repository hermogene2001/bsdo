# BSDO Sale Android App

This is the Android application for the BSDO Sale e-commerce platform with live streaming capabilities.

## Features

### User Authentication
- User registration (client/seller)
- User login/logout
- Session management

### Product Browsing
- Browse products by categories
- Search and filter products
- View product details
- Support for both regular and rental products

### Live Streaming
- Watch live streams from sellers
- Real-time chat during live streams
- WebRTC-based video streaming
- Interactive product showcasing during streams

### Real-time Messaging
- WebSocket-based chat system
- Instant messaging between buyers and sellers
- Push notifications for new messages

### Rental Products
- Special rental pricing
- Daily/weekly rental options
- Dedicated rental product listings

### Seller Features
- Product management (add, edit, delete)
- Live streaming capabilities
- Order management
- Analytics dashboard
- Profile management

### Push Notifications
- Live stream notifications
- Message notifications
- Order status updates

## Technical Stack

- **Language**: Kotlin
- **Architecture**: MVVM with Fragments
- **Networking**: Retrofit, OkHttp
- **Real-time Communication**: WebSocket, WebRTC
- **Image Loading**: Glide
- **UI Components**: Material Design Components
- **Dependency Injection**: None (for simplicity)
- **Database**: None (server-side REST API)

## Setup Instructions

1. Open the project in Android Studio
2. Sync Gradle dependencies
3. Build and run the application

## Project Structure

```
app/
├── src/main/java/com/bsdosale/
│   ├── adapters/           # RecyclerView adapters
│   ├── models/             # Data models
│   ├── services/           # Background services
│   ├── utils/              # Utility classes
│   ├── webrtc/            # WebRTC implementation
│   ├── MainActivity.kt    # Main entry point
│   ├── LoginActivity.kt   # User authentication
│   ├── RegisterActivity.kt # User registration
│   ├── HomeActivity.kt    # Main dashboard
│   ├── ProductsFragment.kt # Product browsing
│   ├── LiveStreamsFragment.kt # Live streaming
│   ├── WatchStreamActivity.kt # Live stream viewer
│   ├── LiveStreamActivity.kt # Seller live streaming
│   ├── SellerDashboardActivity.kt # Seller dashboard
│   └── AddProductActivity.kt # Add new products
├── src/main/res/
│   ├── layout/            # XML layout files
│   ├── drawable/          # Images and drawables
│   ├── values/            # Strings, colors, styles
│   └── menu/              # Menu resources
└── build.gradle           # Dependencies and build config
```

## Dependencies

```gradle
// AndroidX
implementation 'androidx.core:core-ktx:1.9.0'
implementation 'androidx.appcompat:appcompat:1.6.1'
implementation 'com.google.android.material:material:1.8.0'
implementation 'androidx.constraintlayout:constraintlayout:2.1.4'

// Networking
implementation 'com.squareup.retrofit2:retrofit:2.9.0'
implementation 'com.squareup.retrofit2:converter-gson:2.9.0'
implementation 'com.squareup.okhttp3:logging-interceptor:4.10.0'

// Image loading
implementation 'com.github.bumptech.glide:glide:4.14.2'

// WebRTC for live streaming
implementation 'org.webrtc:google-webrtc:1.0.32006'

// WebSocket for real-time messaging
implementation 'org.java-websocket:Java-WebSocket:1.5.3'

// Lifecycle components
implementation 'androidx.lifecycle:lifecycle-viewmodel-ktx:2.6.1'
implementation 'androidx.lifecycle:lifecycle-runtime-ktx:2.6.1'
```

## Permissions Required

- `INTERNET` - Network access
- `CAMERA` - For live streaming
- `RECORD_AUDIO` - For live streaming
- `ACCESS_NETWORK_STATE` - Network state monitoring

## API Endpoints

The app communicates with the BSDO Sale backend through REST APIs. The endpoints include:

- User authentication
- Product management
- Live streaming
- Chat messaging
- Order processing

## Future Enhancements

- Firebase integration for push notifications
- Offline caching
- Enhanced analytics
- Social sharing features
- Payment integration
- Advanced search and filtering
- Wishlist functionality
- Review and rating system

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a pull request

## License

This project is proprietary to BSDO Sale and should not be distributed without permission.