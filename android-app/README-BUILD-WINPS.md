Build instructions (Windows PowerShell)

Prerequisites:
- Android Studio installed (recommended) or at least JDK 11 and Android SDK
- Android SDK platforms for API 33 installed

Open and build with Android Studio:
- Open Android Studio -> Open an existing project -> select `c:\xampp\htdocs\bsdo\android-app`.
- Allow Gradle to sync. Install any required SDK components when prompted.
- Run > Run 'app' to build and install a debug APK on a connected device (enable USB debugging).

Build from command line (using Gradle wrapper if present):
If you don't have a Gradle wrapper in the project, use the Gradle in Android Studio or install Gradle system-wide.

Example PowerShell commands (run in project root):

# Build debug APK
./gradlew assembleDebug

# Build release APK (unsigned unless signing configs added)
./gradlew assembleRelease

If you need a signed release APK, use Android Studio's "Generate Signed Bundle / APK" wizard or add a signingConfig to `app/build.gradle`.

Notes on WebRTC:
- The site must be served over HTTPS for getUserMedia to work in WebView.
- The app requests CAMERA and RECORD_AUDIO permissions at runtime.
