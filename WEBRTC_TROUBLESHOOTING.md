# 🚨 BSDO WebRTC Troubleshooting Guide

## 🔍 **Quick Diagnosis**

If WebRTC isn't working, check these first:

### **1. Browser Compatibility**
```javascript
// Run this in browser console (F12)
console.log('WebRTC Support:', !!window.RTCPeerConnection);
console.log('Media Support:', !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia));
```

### **2. Camera/Microphone Permissions**
- Browser must be `http://localhost` (HTTPS not required for local)
- Click "Allow" when prompted
- Check browser settings: Settings → Privacy → Camera/Microphone

### **3. Network Issues**
- Both users must be on same network for WebRTC
- Check firewall settings (ports 80, 443, STUN ports)
- Try disabling VPN if active

## 🐛 **Common Issues & Fixes**

| Problem | Symptom | Solution |
|---------|---------|----------|
| **Camera not working** | Black screen | Allow permissions, check camera dropdown |
| **No audio** | Can't hear seller | Check microphone permissions, toggle audio |
| **Stream not loading** | Blank video | Check if seller is actually streaming |
| **Chat not working** | Messages not sending | Check network, refresh page |
| **Connection failed** | "Failed to connect" | Check STUN servers, firewall |
| **Multiple viewers** | Only 1 viewer works | WebRTC limitation - use RTMP for many viewers |

## 🔧 **Advanced Debugging**

### **Check Browser Console**
1. Press F12 → Console tab
2. Look for JavaScript errors
3. Check WebRTC connection logs

### **Test Signaling Server**
```bash
# Check if server responds
curl -X POST http://localhost/bsdo/webrtc_server.php \
  -d "action=test"
# Should return authentication error (expected)
```

### **Monitor Network**
- Open Developer Tools → Network tab
- Watch for failed requests
- Check WebRTC connection attempts

## 🎯 **Alternative Testing**

If WebRTC has issues, you can still test:

### **RTMP Streaming (External Software)**
1. Install OBS Studio
2. Use RTMP URL: `rtmp://localhost:1935/live`
3. Stream Key: `[from seller dashboard]`

### **Basic Functionality**
- Test login/logout
- Test product browsing
- Test cart functionality
- Test chat without video

## 📞 **Getting Help**

If issues persist:

1. **Run diagnostics:**
   ```bash
   php test_webrtc_ready.php
   php test_webrtc_full.php
   ```

2. **Check logs:**
   - Browser console (F12)
   - PHP error logs
   - Network tab in dev tools

3. **Test environment:**
   - Try different browsers (Chrome, Firefox, Edge)
   - Test on different computers
   - Check network settings

## 🎉 **Success Checklist**

- [ ] Camera permissions granted
- [ ] Video preview working
- [ ] Audio working
- [ ] Seller can start stream
- [ ] Buyer can join stream
- [ ] Video plays for buyer
- [ ] Chat messages work
- [ ] Products can be featured
- [ ] Multiple tabs work

**Most WebRTC issues are browser permission or network related!** 🔧