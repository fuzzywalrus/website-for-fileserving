#!/bin/bash

# FFmpeg Installation and Hardware Acceleration Setup Script
# For Ubuntu/Debian systems with Intel QuickSync, NVIDIA NVENC, or AMD VCE support

set -e

echo "=========================================="
echo "FFmpeg Hardware Acceleration Setup"
echo "=========================================="
echo ""

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   echo "⚠️  This script should NOT be run as root (don't use sudo)"
   echo "   The script will prompt for sudo when needed"
   exit 1
fi

# Detect system architecture
ARCH=$(uname -m)
echo "📋 System Architecture: $ARCH"
echo ""

# Update package lists
echo "📦 Updating package lists..."
sudo apt update

# Install FFmpeg and basic tools
echo ""
echo "📦 Installing FFmpeg and basic tools..."
sudo apt install -y ffmpeg vainfo

# Check FFmpeg version
FFMPEG_VERSION=$(ffmpeg -version | head -n1)
echo "✅ FFmpeg installed: $FFMPEG_VERSION"
echo ""

# Hardware Acceleration Detection
echo "=========================================="
echo "Hardware Acceleration Detection"
echo "=========================================="
echo ""

HW_ACCEL_DETECTED="none"
HW_ACCEL_DETAILS=""

# Check for Intel QuickSync (VA-API)
echo "🔍 Checking for Intel QuickSync (VA-API)..."
if [[ -e /dev/dri/renderD128 ]]; then
    echo "   ✅ /dev/dri/renderD128 found"
    
    # Install Intel media drivers
    echo "   📦 Installing Intel media drivers..."
    sudo apt install -y intel-media-va-driver i965-va-driver vainfo
    
    # Test VA-API
    if vainfo > /dev/null 2>&1; then
        VAINFO_OUTPUT=$(vainfo 2>&1)
        if echo "$VAINFO_OUTPUT" | grep -q "Intel"; then
            echo "   ✅ Intel VA-API detected and working"
            HW_ACCEL_DETECTED="intel"
            HW_ACCEL_DETAILS="Intel QuickSync via VA-API (h264_vaapi, hevc_vaapi)"
            
            # Test encoding capability
            echo "   🧪 Testing H.264 encoding with VA-API..."
            if ffmpeg -hide_banner -loglevel error -f lavfi -i testsrc=duration=1:size=320x240:rate=1 -c:v h264_vaapi -vaapi_device /dev/dri/renderD128 -vf 'format=nv12,hwupload' -f null - 2>&1; then
                echo "   ✅ H.264 VA-API encoding test successful"
            else
                echo "   ⚠️  H.264 VA-API encoding test failed"
            fi
        else
            echo "   ⚠️  VA-API found but not Intel"
        fi
    else
        echo "   ⚠️  VA-API drivers installed but not working properly"
    fi
else
    echo "   ❌ /dev/dri/renderD128 not found - Intel QuickSync not available"
fi

echo ""

# Check for NVIDIA NVENC
echo "🔍 Checking for NVIDIA GPU (NVENC)..."
if command -v nvidia-smi &> /dev/null; then
    NVIDIA_INFO=$(nvidia-smi --query-gpu=name --format=csv,noheader 2>/dev/null || echo "")
    if [[ -n "$NVIDIA_INFO" ]]; then
        echo "   ✅ NVIDIA GPU detected: $NVIDIA_INFO"
        
        # Check if CUDA is installed
        if command -v nvcc &> /dev/null; then
            CUDA_VERSION=$(nvcc --version | grep "release" | awk '{print $5}' | cut -d',' -f1)
            echo "   ✅ CUDA installed: $CUDA_VERSION"
        else
            echo "   ⚠️  CUDA not found. Installing CUDA toolkit is recommended for NVENC"
            echo "      Visit: https://developer.nvidia.com/cuda-downloads"
        fi
        
        # Test NVENC encoding capability
        echo "   🧪 Testing H.264 encoding with NVENC..."
        if ffmpeg -hide_banner -loglevel error -f lavfi -i testsrc=duration=1:size=320x240:rate=1 -c:v h264_nvenc -f null - 2>&1; then
            echo "   ✅ H.264 NVENC encoding test successful"
            if [[ "$HW_ACCEL_DETECTED" == "none" ]]; then
                HW_ACCEL_DETECTED="nvidia"
                HW_ACCEL_DETAILS="NVIDIA NVENC (h264_nvenc, hevc_nvenc)"
            fi
        else
            echo "   ⚠️  H.264 NVENC encoding test failed - drivers may need updating"
        fi
    else
        echo "   ❌ nvidia-smi found but no GPU detected"
    fi
else
    echo "   ❌ nvidia-smi not found - NVIDIA GPU not available"
fi

echo ""

# Check for AMD GPU (VA-API)
echo "🔍 Checking for AMD GPU (VA-API)..."
if lspci | grep -i "VGA.*AMD" > /dev/null 2>&1; then
    AMD_GPU=$(lspci | grep -i "VGA.*AMD" | head -n1)
    echo "   ✅ AMD GPU detected: $AMD_GPU"
    
    # Install AMD drivers
    echo "   📦 Installing AMD VA-API drivers..."
    sudo apt install -y mesa-va-drivers
    
    # Check if VA-API works with AMD
    if [[ -e /dev/dri/renderD128 ]] && vainfo 2>&1 | grep -qi "AMD\|Radeon"; then
        echo "   ✅ AMD VA-API detected and working"
        if [[ "$HW_ACCEL_DETECTED" == "none" ]]; then
            HW_ACCEL_DETECTED="amd"
            HW_ACCEL_DETAILS="AMD VCE via VA-API (h264_vaapi, hevc_vaapi)"
        fi
        
        # Test encoding capability
        echo "   🧪 Testing H.264 encoding with VA-API..."
        if ffmpeg -hide_banner -loglevel error -f lavfi -i testsrc=duration=1:size=320x240:rate=1 -c:v h264_vaapi -vaapi_device /dev/dri/renderD128 -vf 'format=nv12,hwupload' -f null - 2>&1; then
            echo "   ✅ H.264 VA-API encoding test successful"
        else
            echo "   ⚠️  H.264 VA-API encoding test failed"
        fi
    else
        echo "   ⚠️  AMD GPU found but VA-API not working properly"
    fi
else
    echo "   ❌ No AMD GPU detected"
fi

echo ""
echo "=========================================="
echo "Detection Summary"
echo "=========================================="
echo ""

if [[ "$HW_ACCEL_DETECTED" != "none" ]]; then
    echo "✅ Hardware Acceleration Available"
    echo "   Type: $HW_ACCEL_DETECTED"
    echo "   Details: $HW_ACCEL_DETAILS"
    echo ""
    echo "📝 Recommended .env configuration:"
    echo "   HW_ACCEL=auto"
    echo "   (or set to: $HW_ACCEL_DETECTED)"
else
    echo "⚠️  No hardware acceleration detected"
    echo "   FFmpeg will use software encoding (CPU only)"
    echo "   This will work but may be slower and use more CPU"
    echo ""
    echo "📝 Recommended .env configuration:"
    echo "   HW_ACCEL=none"
fi

echo ""
echo "=========================================="
echo "Additional Setup"
echo "=========================================="
echo ""

# Add user to video and render groups for GPU access
echo "👤 Adding current user to video/render groups for GPU access..."
sudo usermod -a -G video $USER
sudo usermod -a -G render $USER 2>/dev/null || echo "   ℹ️  render group not available (older system)"

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "⚠️  IMPORTANT: You must log out and log back in for group changes to take effect"
echo ""
echo "Next steps:"
echo "1. Log out and log back in (or reboot)"
echo "2. Create your .env file with streaming configuration"
echo "3. Set ENABLE_STREAMING=true"
echo "4. Set HW_ACCEL=$HW_ACCEL_DETECTED (or 'auto')"
echo "5. Test streaming with a video file"
echo ""
echo "For more information, see the README.md file"
echo ""
