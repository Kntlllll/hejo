#!/bin/bash

# Direktori kerja
WORK_DIR="/var/www/html"

# Konfigurasi file (semua di /var/www/html/)
INPUT_FILE="$WORK_DIR/data.json"
FAILED_FILE="$WORK_DIR/failed.json"
SUCCESS_FILE="$WORK_DIR/success.json"
LOG_FILE="$WORK_DIR/execution.log"

# Konfigurasi API
AUTH_TOKEN="MTIzOjE3MjIwNTk5MzA="
API_URL="https://cybervpn.my.id/tembak/configuration/smartfren/api-multibuy?action=purchase"

# Delay antar request (dalam detik)
REQUEST_DELAY=5

# Fungsi untuk log
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Fungsi untuk inisialisasi file JSON
init_json_file() {
    local file="$1"
    
    if [ ! -f "$file" ]; then
        echo "[]" > "$file"
        log "File $file dibuat dengan struktur JSON kosong"
    elif [ ! -s "$file" ]; then
        echo "[]" > "$file"
        log "File $file diinisialisasi ulang karena kosong"
    fi
}

# Fungsi untuk validasi file JSON
validate_json_file() {
    local file="$1"
    
    if [ -f "$file" ] && [ -s "$file" ]; then
        if ! jq empty "$file" 2>/dev/null; then
            log "ERROR: File $file bukan JSON valid. Membuat yang baru..."
            echo "[]" > "$file"
            return 1
        fi
        return 0
    else
        echo "[]" > "$file"
        return 0
    fi
}

# Fungsi untuk eksekusi curl
execute_curl() {
    local msisdn="$1"
    local nama_paket="$2"
    
    log "Memproses MSISDN: $msisdn - Paket: $nama_paket"
    
    # Eksekusi curl dengan timeout
    response=$(curl -s -w "\n%{http_code}" \
        -m 30 \
        -X POST "$API_URL" \
        -H "Authorization: Bearer $AUTH_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{
            "msisdn": "'"$msisdn"'",
            "nama_paket": "'"$nama_paket"'",
            "payment_method": "pulsa"
        }')
    
    # Pisahkan response body dan status code
    http_code=$(echo "$response" | tail -n1)
    response_body=$(echo "$response" | sed '$d')
    
    log "Response HTTP Code: $http_code"
    log "Response Body: $response_body"
    
    # Cek apakah curl berhasil
    if [ $? -ne 0 ]; then
        log "ERROR: Gagal melakukan request curl untuk $msisdn"
        return 2
    fi
    
    # Cek apakah sukses
    if echo "$response_body" | grep -q '"success":true' && [ "$http_code" -eq 200 ]; then
        log "SUKSES: Transaksi berhasil untuk $msisdn"
        
        # Validasi response JSON sebelum menyimpan
        if echo "$response_body" | jq empty 2>/dev/null; then
            # Simpan ke file sukses
            if [ ! -f "$SUCCESS_FILE" ]; then
                echo "[]" > "$SUCCESS_FILE"
            fi
            
            # Tambahkan ke success.json
            jq --argjson new "$response_body" '. += [$new]' "$SUCCESS_FILE" > "${SUCCESS_FILE}.tmp" && \
                mv "${SUCCESS_FILE}.tmp" "$SUCCESS_FILE"
            
            log "Data sukses disimpan ke $SUCCESS_FILE"
        else
            log "WARNING: Response bukan JSON valid untuk $msisdn"
        fi
        
        return 0
    else
        log "GAGAL: Transaksi untuk $msisdn"
        
        # Cek tipe error
        if echo "$response_body" | grep -qi "saldo\|balance\|pulsa"; then
            log "ERROR: Kemungkinan saldo habis untuk $msisdn"
        elif echo "$response_body" | grep -qi "error\|failed\|gagal"; then
            log "ERROR: Response error dari API untuk $msisdn"
        fi
        
        # Tambahkan ke failed.json
        if [ ! -f "$FAILED_FILE" ]; then
            echo "[]" > "$FAILED_FILE"
        fi
        
        # Tambahkan ke failed.json
        jq --arg msisdn "$msisdn" \
           --arg paket "$nama_paket" \
           --arg http_code "$http_code" \
           --arg response "$response_body" \
           '. += [{
               "msisdn": $msisdn, 
               "nama_paket": $paket,
               "http_code": $http_code,
               "response": $response,
               "timestamp": "'"$(date '+%Y-%m-%d %H:%M:%S')"'"
           }]' "$FAILED_FILE" > "${FAILED_FILE}.tmp" && \
        mv "${FAILED_FILE}.tmp" "$FAILED_FILE"
        
        log "Data gagal disimpan ke $FAILED_FILE"
        
        return 1
    fi
}

# Fungsi untuk memproses file JSON
process_numbers() {
    local input_file="$1"
    local temp_file="${input_file}.tmp"
    
    # Validasi file input
    if ! validate_json_file "$input_file"; then
        log "ERROR: File $input_file tidak valid"
        return 1
    fi
    
    # Hitung total nomor
    total=$(jq 'length' "$input_file")
    
    if [ "$total" -eq 0 ]; then
        log "INFO: Tidak ada data untuk diproses di $input_file"
        return 0
    fi
    
    log "Memulai proses $total nomor dari $input_file"
    
    # Buat salinan file input
    cp "$input_file" "$temp_file"
    
    # Loop melalui semua nomor
    for i in $(seq 0 $((total - 1))); do
        # Ambil data dengan jq
        msisdn=$(jq -r ".[$i].msisdn" "$temp_file")
        nama_paket=$(jq -r ".[$i].nama_paket" "$temp_file")
        
        # Cek jika msisdn valid
        if [ -z "$msisdn" ] || [ "$msisdn" = "null" ]; then
            log "WARNING: MSISDN tidak valid pada index $i, melanjutkan..."
            continue
        fi
        
        current=$((i + 1))
        log "Progress: $current/$total - MSISDN: $msisdn"
        
        # Eksekusi curl
        execute_curl "$msisdn" "$nama_paket"
        curl_exit_code=$?
        
        # Jika sukses, hapus dari file temporary
        if [ $curl_exit_code -eq 0 ]; then
            jq --arg msisdn "$msisdn" \
                '[.[] | select(.msisdn != $msisdn)]' \
                "$temp_file" > "${temp_file}.new" && \
                mv "${temp_file}.new" "$temp_file"
            
            # Update total count setelah menghapus
            total=$(jq 'length' "$temp_file")
        fi
        
        # Delay antar request (kecuali untuk request terakhir)
        if [ $current -lt $total ] || [ -f "$input_file" ] && [ $(jq 'length' "$input_file") -gt 0 ]; then
            log "Menunggu $REQUEST_DELAY detik sebelum request berikutnya..."
            sleep $REQUEST_DELAY
        fi
        
        # Backup berkala setiap 100 request
        if [ $((current % 100)) -eq 0 ]; then
            log "Checkpoint: $current nomor diproses"
            if [ -f "$temp_file" ]; then
                backup_file="${WORK_DIR}/backup_data_$(date +%Y%m%d_%H%M%S).json"
                cp "$temp_file" "$backup_file"
                log "Backup dibuat: $backup_file"
                
                # Hapus backup lama (simpan hanya 5 terakhir)
                ls -t ${WORK_DIR}/backup_data_*.json 2>/dev/null | tail -n +6 | xargs rm -f 2>/dev/null
            fi
        fi
    done
    
    # Update file asli dengan data yang tersisa
    if [ -f "$temp_file" ]; then
        mv "$temp_file" "$input_file"
        remaining=$(jq 'length' "$input_file")
        log "Proses selesai. $remaining nomor tersisa di $input_file"
    fi
    
    # Cleanup
    rm -f "${temp_file}.new" 2>/dev/null
    
    return 0
}

# Fungsi untuk retry nomor yang gagal
retry_failed_numbers() {
    if [ ! -f "$FAILED_FILE" ] || [ ! -s "$FAILED_FILE" ]; then
        log "INFO: Tidak ada nomor gagal untuk di-retry"
        return 0
    fi
    
    failed_count=$(jq 'length' "$FAILED_FILE")
    log "=== MULAI RETRY: $failed_count nomor gagal ==="
    
    # Buat file temporary untuk retry
    retry_file="$WORK_DIR/retry.json"
    echo "[]" > "$retry_file"
    
    # Ekstrak hanya msisdn dan nama_paket untuk retry
    jq '[.[] | {msisdn: .msisdn, nama_paket: .nama_paket}]' "$FAILED_FILE" > "$retry_file"
    
    # Proses retry
    process_numbers "$retry_file"
    
    # Update failed.json dengan yang masih gagal
    if [ -f "$retry_file" ] && [ -s "$retry_file" ]; then
        retry_remaining=$(jq 'length' "$retry_file")
        
        if [ "$retry_remaining" -gt 0 ]; then
            # Buat failed.json baru dengan data retry yang gagal
            jq '[.[] | {
                msisdn: .msisdn,
                nama_paket: .nama_paket,
                http_code: "RETRY_FAILED",
                response: "Masih gagal setelah retry",
                timestamp: "'"$(date '+%Y-%m-%d %H:%M:%S')"'"
            }]' "$retry_file" > "${FAILED_FILE}.new"
            
            # Gabungkan dengan failed lama (jika ada entry baru selama retry)
            if [ -f "$FAILED_FILE" ] && [ -s "$FAILED_FILE" ]; then
                jq -s '.[0] + .[1]' "${FAILED_FILE}.new" "$FAILED_FILE" > "${FAILED_FILE}.combined"
                mv "${FAILED_FILE}.combined" "$FAILED_FILE"
            else
                mv "${FAILED_FILE}.new" "$FAILED_FILE"
            fi
            
            log "$retry_remaining nomor masih gagal setelah retry"
        else
            log "SEMUA RETRY BERHASIL!"
            # Kosongkan failed.json
            echo "[]" > "$FAILED_FILE"
        fi
        
        rm -f "$retry_file"
    else
        log "Semua retry berhasil atau tidak ada data retry"
        echo "[]" > "$FAILED_FILE"
    fi
    
    # Cleanup file temporary
    rm -f "${FAILED_FILE}.new" "${FAILED_FILE}.combined" 2>/dev/null
}

# Main execution
main() {
    # Cek apakah running sebagai root atau memiliki akses ke /var/www/html
    if [ ! -w "$WORK_DIR" ]; then
        echo "ERROR: Tidak memiliki akses tulis ke $WORK_DIR"
        echo "Jalankan dengan sudo atau pastikan user memiliki permission"
        exit 1
    fi
    
    # Pindah ke working directory
    cd "$WORK_DIR" || {
        echo "ERROR: Tidak bisa pindah ke $WORK_DIR"
        exit 1
    }
    
    # Cek dependensi
    if ! command -v jq &> /dev/null; then
        echo "ERROR: jq diperlukan. Install dengan: sudo apt-get install jq"
        exit 1
    fi
    
    if ! command -v curl &> /dev/null; then
        echo "ERROR: curl diperlukan. Install dengan: sudo apt-get install curl"
        exit 1
    fi
    
    # Cek file input
    if [ ! -f "$INPUT_FILE" ]; then
        echo "ERROR: File $INPUT_FILE tidak ditemukan di $WORK_DIR"
        echo "Buat file $INPUT_FILE dengan format JSON terlebih dahulu"
        exit 1
    fi
    
    # Inisialisasi file output
    init_json_file "$SUCCESS_FILE"
    init_json_file "$FAILED_FILE"
    
    # Validasi file input
    if ! validate_json_file "$INPUT_FILE"; then
        echo "ERROR: File $INPUT_FILE tidak berformat JSON valid"
        exit 1
    fi
    
    # Inisialisasi file log
    echo "=== Log Eksekusi - Dimulai pada $(date) ===" > "$LOG_FILE"
    echo "Working Directory: $WORK_DIR" >> "$LOG_FILE"
    echo "Delay antar request: ${REQUEST_DELAY} detik" >> "$LOG_FILE"
    echo "==========================================" >> "$LOG_FILE"
    
    log "Skrip dimulai"
    log "Jumlah data awal: $(jq 'length' "$INPUT_FILE") nomor"
    
    # Proses nomor utama
    log "=== PROSES UTAMA DIMULAI ==="
    process_numbers "$INPUT_FILE"
    
    # Tunggu sebelum retry
    log "Menunggu 10 detik sebelum retry..."
    sleep 10
    
    # Proses retry untuk nomor yang gagal
    log "=== PROSES RETRY DIMULAI ==="
    retry_failed_numbers
    
    # Summary
    log "=== RINGKASAN EKSEKUSI ==="
    log "Total sukses: $(jq 'length' "$SUCCESS_FILE")"
    log "Total gagal: $(jq 'length' "$FAILED_FILE")"
    log "Sisa di input: $(jq 'length' "$INPUT_FILE")"
    log "=== EKSEKUSI SELESAI ==="
    
    echo ""
    echo "========================================"
    echo "EKSEKUSI SELESAI!"
    echo "========================================"
    echo "Lokasi file di: $WORK_DIR"
    echo "Log lengkap     : $LOG_FILE"
    echo "Data sukses     : $SUCCESS_FILE ($(jq 'length' "$SUCCESS_FILE") entries)"
    echo "Data gagal      : $FAILED_FILE ($(jq 'length' "$FAILED_FILE") entries)"
    echo "Data tersisa    : $INPUT_FILE ($(jq 'length' "$INPUT_FILE") entries)"
    echo "========================================"
}

# Trap untuk menangani interrupt (Ctrl+C)
trap 'log "Script diinterrupt oleh user. Menyimpan state..."; exit 1' INT TERM

# Jalankan main function
main "$@"
