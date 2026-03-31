@echo off
title Install Python GPU Dependencies untuk Excel Import
color 0A
echo.
echo ============================================================
echo   INSTALLER PYTHON GPU DEPENDENCIES
echo   Untuk Excel Import Akselerasi GPU/CPU
echo ============================================================
echo.

:: Cek apakah Python sudah terinstall
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Python tidak ditemukan!
    echo.
    echo Silakan install Python 3.11+ terlebih dahulu:
    echo   https://www.python.org/downloads/
    echo.
    echo PENTING: Centang "Add Python to PATH" saat instalasi!
    echo.
    pause
    exit /b 1
)

echo [OK] Python ditemukan:
python --version
echo.

:: Upgrade pip
echo [1/4] Upgrade pip...
python -m pip install --upgrade pip
echo.

:: Install dependencies utama
echo [2/4] Install pandas + openpyxl (baca Excel)...
pip install pandas openpyxl python-dateutil
echo.

echo [3/4] Install pymysql (koneksi MySQL)...
pip install pymysql
echo.

echo [4/4] Cek apakah NVIDIA GPU tersedia untuk cuDF...
echo.

:: Cek NVIDIA GPU
nvidia-smi >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] NVIDIA GPU terdeteksi!
    nvidia-smi --query-gpu=name,driver_version,memory.total --format=csv,noheader
    echo.
    echo Mencoba install cuDF (RAPIDS) untuk GPU acceleration...
    echo CATATAN: cuDF membutuhkan CUDA 11.x atau 12.x
    echo.
    
    :: Cek versi CUDA
    nvcc --version >nul 2>&1
    if %errorlevel% equ 0 (
        echo CUDA ditemukan:
        nvcc --version
        echo.
        echo Install cuDF untuk CUDA 12...
        pip install cudf-cu12 --extra-index-url=https://pypi.nvidia.com
        if %errorlevel% equ 0 (
            echo [OK] cuDF berhasil diinstall! GPU acceleration aktif.
        ) else (
            echo [WARN] cuDF gagal diinstall. Coba CUDA 11:
            pip install cudf-cu11 --extra-index-url=https://pypi.nvidia.com
            if %errorlevel% equ 0 (
                echo [OK] cuDF (CUDA 11) berhasil diinstall!
            ) else (
                echo [WARN] cuDF tidak bisa diinstall.
                echo        Kemungkinan: CUDA tidak kompatibel atau Windows tidak didukung RAPIDS.
                echo        Akan menggunakan pandas (CPU) sebagai fallback.
                echo        pandas tetap 10-100x lebih cepat dari PhpSpreadsheet!
            )
        )
    ) else (
        echo [WARN] CUDA toolkit tidak ditemukan.
        echo        Install CUDA dari: https://developer.nvidia.com/cuda-downloads
        echo        Akan menggunakan pandas (CPU) sebagai fallback.
    )
) else (
    echo [INFO] NVIDIA GPU tidak terdeteksi atau driver belum terinstall.
    echo        Akan menggunakan pandas (CPU) sebagai fallback.
    echo        pandas tetap 10-100x lebih cepat dari PhpSpreadsheet!
)

echo.
echo ============================================================
echo   VERIFIKASI INSTALASI
echo ============================================================
echo.

python -c "import pandas; print('[OK] pandas', pandas.__version__)"
python -c "import openpyxl; print('[OK] openpyxl', openpyxl.__version__)"
python -c "import pymysql; print('[OK] pymysql', pymysql.__version__)"
python -c "import dateutil; print('[OK] python-dateutil', dateutil.__version__)"

python -c "import cudf; print('[OK] cuDF (GPU)', cudf.__version__)" 2>nul
if %errorlevel% neq 0 (
    echo [INFO] cuDF tidak tersedia - akan pakai pandas CPU
)

echo.
echo ============================================================
echo   SELESAI! Sekarang import Excel akan menggunakan:
echo   - GPU (cuDF) jika tersedia
echo   - CPU (pandas) sebagai fallback
echo   Keduanya jauh lebih cepat dari PhpSpreadsheet!
echo ============================================================
echo.
pause
