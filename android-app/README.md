BSDO Android WebView Wrapper

This minimal Android Studio project wraps the BSDO website (https://www.bsdosale.com/) in a WebView, enabling cookies, file uploads, and WebRTC (camera/microphone) support.

What is included
- A minimal Android Studio project skeleton under `android-app/`.
- `MainActivity` with WebView setup, WebChromeClient for permissions and file chooser.
- `AndroidManifest.xml` with required permissions and configuration.
- Gradle wrapper files and build files (minimal). 

Notes
- The app assumes the website is served over HTTPS. WebRTC in Android WebView requires HTTPS.
- Test on Android 8+ devices. Some OEM WebViews vary; test thoroughly.

Build instructions (Windows PowerShell)
1. Install Android Studio, JDK 11+, and Android SDK if you haven't already.
2. Open `android-app` in Android Studio.
3. Let Gradle sync and install required SDK components.
4. Build a debug APK via "Run" or Build > Build Bundle(s) / APK(s) > Build APK(s).

To create a release-signed APK:
1. Build > Generate Signed Bundle / APK...
2. Follow the wizard to create or use an existing signing key.

If you want I can add an adaptive app icon and customize the toolbar.
 
Deep links
- The app is configured to open `https://www.bsdosale.com/*` links directly in the app (intent filter with autoVerify). To enable Android App Links verification, add the appropriate Digital Asset Links JSON to your website under `https://www.bsdosale.com/.well-known/assetlinks.json` and include the app's package name and signing key fingerprint.

Signing (quick)
- A sample `create_and_build_signed.ps1` script is included to create a keystore (if missing), write `keystore.properties`, and run Gradle to build the release. The script will write `keystore.properties` in the project root — DO NOT commit that file to version control.

Next steps
- Open the project in Android Studio to let it generate Gradle wrappers if needed, then build and test on a device.
