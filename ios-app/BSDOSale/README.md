# BSDO Sale iOS App

This is a simple iOS app that displays the content from https://bsdosale.com/ using a WKWebView.

## Prerequisites

- Xcode 12.0 or later
- iOS 13.0 or later

## How to Build

1. Open Xcode
2. Create a new iOS App project
3. Select "Storyboard" for the Interface option
4. Select "UIKit App Delegate" for the Life Cycle option
5. Select "Swift" for the Language option
6. Replace the generated files with the files from this directory:
   - AppDelegate.swift
   - SceneDelegate.swift
   - ViewController.swift
   - Main.storyboard
   - Info.plist
   - Assets.xcassets (folder)
7. Add the "WebKit" framework to your project:
   - Click on your project in the Project Navigator
   - Select your target
   - Go to "Frameworks, Libraries, and Embedded Content"
   - Click the "+" button
   - Search for "WebKit" and add it

## App Structure

- **AppDelegate.swift**: The main application delegate
- **SceneDelegate.swift**: The scene delegate for managing the app's scenes
- **ViewController.swift**: The main view controller that contains the WKWebView
- **Main.storyboard**: The main storyboard file
- **Info.plist**: The app's configuration file
- **Assets.xcassets**: Contains the app icons and other assets

## Features

- Displays the BSDO Sale website in a WKWebView
- Supports navigation within the website
- Responsive design that works on both iPhone and iPad

## Customization

To customize the app:

1. To change the URL, modify the following line in ViewController.swift:
   ```swift
   let myURL = URL(string: "https://bsdosale.com/")
   ```

2. To change the app name, modify the "Bundle display name" in Info.plist

3. To change the app icon, replace the images in Assets.xcassets/AppIcon.appiconset

## Notes

- The app requires an internet connection to function properly
- All website functionality should work as expected
- The app supports both portrait and landscape orientations