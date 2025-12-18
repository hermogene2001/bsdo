# 🧪 BSDO WebRTC Testing Guide

## 🎯 **Quick Test - No Authentication Required**

I've created a test page that checks your WebRTC setup without needing to log in:

**Open:** `http://localhost/bsdo/webrtc_test_page.html`

This page tests:
- ✅ Camera/Microphone access
- ✅ WebRTC browser support  
- ✅ Network connectivity
- ✅ Signaling server connectivity

## 🚀 **Full WebRTC Streaming Test**

### **Test Accounts Created:**
- **Seller:** `seller@test.com` / `test123`
- **Buyer:** `buyer@test.com` / `test123`

### **Step-by-Step Testing:**

#### **1. Test Basic Functionality**
```bash
# Open the test page
start http://localhost/bsdo/webrtc_test_page.html
```

#### **2. Test Seller Streaming**
```bash
# Open browser and login as seller
start http://localhost/bsdo/login.php
# Login: seller@test.com / test123
# Then go to: seller/live_stream.php
# Select "WebRTC (Browser-based streaming)"
# Click "Start Live Stream"
```

#### **3. Test Buyer Viewing**
```bash
# Open another browser/incognito window
start http://localhost/bsdo/login.php
# Login: buyer@test.com / test123  
# Go to: live.php
# Click "Join Stream" on the active stream
```

## 🔧 **Troubleshooting**

### **Camera/Microphone Not Working:**
1. Make sure you're on `http://localhost` (HTTPS not required for local testing)
2. Click "Allow" when browser asks for camera permission
3. Check browser settings for camera/microphone access

### **"Signaling Server Not Responding":**
- This is expected if you're not logged in
- The signaling server requires authentication
- Test with logged-in accounts instead

### **Video Not Showing:**
1. Check browser console (F12) for JavaScript errors
2. Make sure camera permissions are granted
3. Try refreshing the page
4. Test with a different browser (Chrome recommended)

### **Connection Issues:**
1. Check if both users are on the same network
2. Try with different browsers
3. Check firewall settings
4. Verify STUN servers are accessible

## 📋 **Expected Behavior**

### **Seller Side:**
- Camera preview appears immediately
- Can select different cameras/microphones
- Can toggle video/audio on/off
- Can add products to showcase
- Chat messages appear in real-time

### **Buyer Side:**
- Video stream loads automatically
- Can send chat messages
- Can see featured products
- Can add products to cart

## 🎯 **Success Indicators**

✅ **Camera access granted**  
✅ **Video preview working**  
✅ **Audio working (test with microphone)**  
✅ **Chat messages send/receive**  
✅ **Products can be featured**  
✅ **Multiple viewers can join**  

## 🐛 **Common Issues & Fixes**

| Issue | Solution |
|-------|----------|
| Camera blocked | Allow permissions in browser |
| No video | Check camera selection dropdown |
| No audio | Check microphone selection |
| Chat not working | Check network connection |
| Stream not loading | Verify seller is actually streaming |

## 📞 **Need Help?**

If tests fail:

1. **Run the test page:** `webrtc_test_page.html`
2. **Check browser console** for errors (F12 → Console)
3. **Verify accounts exist** by running `php test_webrtc_full.php`
4. **Test with different browsers** (Chrome, Firefox, Edge)

## 🎉 **Ready to Test!**

Your WebRTC streaming system is fully configured. Follow the steps above to test the complete streaming workflow!

**Test Results Expected:**
- Seller can stream directly from browser
- Buyers can watch and interact in real-time
- No external software required
- Works on any modern browser