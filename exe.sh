#!/bin/bash

# Direktori kerja
WORK_DIR="/var/www/html"

# Konfigurasi file (semua di /var/www/html/)
INPUT_FILE="$WORK_DIR/data.json"
FAILED_FILE="$WORK_DIR/failed.json"
SUCCESS_FILE="$WORK_DIR/success.json"
LOG_FILE="$WORK_DIR/execution.log"
PROCESSING_FILE="$WORK_DIR/processing.json"

# Konfigurasi API
AUTH_TOKEN="MTIzOjE3MjIwNTk5MzA="
API_URL="https://cybervpn.my.id/tembak/configuration/smartfren/api-multibuy?action=purchase"

# Konfigurasi performa
REQUEST_DELAY=5
MAX_RETRIES=2
BATCH_SIZE=100
MAX_REQUESTS_PER_MINUTE=12  # Rate limiting: 12 requests/minute = 5 detik delay

# Fungsi untuk log dengan log level
log() {
    local level="INFO"
    local message="$1"
    
    # Deteksi level dari pesan
    if [[ "$message" == ERROR:* ]]; then
        level="ERROR"
    elif [[ "$message" == WARNING:* ]]; then
        level="WARNING"
    elif [[ "$message" == SUCCESS:* ]]; then
        level="SUCCESS"
    fi
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$level] ${message}" | tee -a "$LOG_FILE"
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

# Fungsi untuk deduplikasi dan validasi data input
deduplicate_and_validate() {
    log "Memulai deduplikasi dan validasi data..."
    
    local original_count=$(jq 'length' "$INPUT_FILE" 2>/dev/null || echo "0")
    log "Jumlah data awal: $original_count"
    
    # Step 1: Filter data dengan msisdn null atau kosong
    jq '[.[] | select(.msisdn != null and .msisdn != "" and .msisdn != "null")]' \
        "$INPUT_FILE" > "${INPUT_FILE}.tmp1"
    
    # Step 2: Hapus duplikat dalam file input itu sendiri
    jq 'group_by(.msisdn) | map(.[0])' "${INPUT_FILE}.tmp1" > "${INPUT_FILE}.tmp2"
    
    # Step 3: Filter out numbers already in success.json
    if [ -f "$SUCCESS_FILE" ] && [ -s "$SUCCESS_FILE" ]; then
        log "Filtering numbers already in success.json..."
        jq --slurpfile success "$SUCCESS_FILE" '
            [.[] | select(
                .msisdn as $m | 
                ($success[0] | any(.msisdn? == $m) | not)
            )]
        ' "${INPUT_FILE}.tmp2" > "${INPUT_FILE}.tmp3"
        mv "${INPUT_FILE}.tmp3" "${INPUT_FILE}.tmp2"
    fi
    
    # Step 4: Filter out numbers already in failed.json (opsional)
    if [ -f "$FAILED_FILE" ] && [ -s "$FAILED_FILE" ]; then
        log "Filtering numbers in failed.json (akan di-retry nanti)..."
        # Tetap simpan di failed.json untuk retry logic terpisah
    fi
    
    # Hitung perubahan
    local new_count=$(jq 'length' "${INPUT_FILE}.tmp2" 2>/dev/null || echo "0")
    local removed_duplicates=$((original_count - new_count))
    
    if [ "$removed_duplicates" -gt 0 ]; then
        log "Removed $removed_duplicates duplicate/invalid entries"
    fi
    
    # Update file asli
    mv "${INPUT_FILE}.tmp2" "$INPUT_FILE"
    rm -f "${INPUT_FILE}.tmp1" 2>/dev/null
    
    log "Jumlah data setelah deduplikasi: $new_count"
    return 0
}

# Fungsi untuk rate limiting
rate_limit() {
    local last_request_file="$WORK_DIR/.last_request"
    local current_time=$(date +%s)
    local last_time=0
    
    if [ -f "$last_request_file" ]; then
        last_time=$(cat "$last_request_file")
    fi
    
    local time_diff=$((current_time - last_time))
    
    if [ "$time_diff" -lt "$REQUEST_DELAY" ]; then
        local sleep_time=$((REQUEST_DELAY - time_diff))
        log "Rate limiting: Tunggu $sleep_time detik..."
        sleep "$sleep_time"
    fi
    
    echo "$current_time" > "$last_request_file"
}

# Fungsi untuk eksekusi curl dengan retry
execute_curl() {
    local msisdn="$1"
    local nama_paket="$2"
    local attempt=1
    local max_attempts=2
    
    # Cek dulu apakah sudah sukses sebelumnya (safety double check)
    if [ -f "$SUCCESS_FILE" ] && [ -s "$SUCCESS_FILE" ]; then
        if jq --arg m "$msisdn" \
            '[.[] | select(.msisdn? == $m)] | length' \
            "$SUCCESS_FILE" 2>/dev/null | grep -q '[1-9]'; then
            log "WARNING: $msisdn sudah sukses sebelumnya. Skip..."
            return 0
        fi
    fi
    
    while [ $attempt -le $max_attempts ]; do
        log "Attempt $attempt/$max_attempts: MSISDN: $msisdn - Paket: $nama_paket"
        
        # Rate limiting sebelum request
        rate_limit
        
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
        
        # Cek apakah curl berhasil
        if [ $? -ne 0 ]; then
            log "ERROR: Gagal melakukan request curl untuk $msisdn (attempt $attempt)"
            attempt=$((attempt + 1))
            sleep 2
            continue
        fi
        
        # Cek apakah sukses
        if echo "$response_body" | grep -q '"success":true' && [ "$http_code" -eq 200 ]; then
            log "SUCCESS: Transaksi berhasil untuk $msisdn"
            
            # Validasi response JSON sebelum menyimpan
            if echo "$response_body" | jq empty 2>/dev/null; then
                # Simpan ke file sukses
                if [ ! -f "$SUCCESS_FILE" ]; then
                    echo "[]" > "$SUCCESS_FILE"
                fi
                
                # Tambahkan original msisdn ke response
                enhanced_response=$(echo "$response_body" | jq --arg msisdn "$msisdn" '. + {original_msisdn: $msisdn}')
                
                # Tambahkan ke success.json
                jq --argjson new "$enhanced_response" '. += [$new]' "$SUCCESS_FILE" > "${SUCCESS_FILE}.tmp" && \
                    mv "${SUCCESS_FILE}.tmp" "$SUCCESS_FILE"
                
                log "Data sukses disimpan ke $SUCCESS_FILE"
            else
                log "WARNING: Response bukan JSON valid untuk $msisdn"
                # Simpan sebagai gagal jika response tidak valid
                save_failed "$msisdn" "$nama_paket" "$http_code" "$response_body"
                return 1
            fi
            
            return 0
        else
            log "GAGAL: Transaksi untuk $msisdn (attempt $attempt)"
            
            # Cek apakah perlu retry
            if [ $attempt -lt $max_attempts ]; then
                if echo "$response_body" | grep -qi "timeout\|busy\|temporary"; then
                    log "ERROR: Temporary error, akan retry..."
                    attempt=$((attempt + 1))
                    sleep 3
                    continue
                fi
            fi
            
            # Simpan ke failed.json
            save_failed "$msisdn" "$nama_paket" "$http_code" "$response_body"
            return 1
        fi
    done
    
    # Jika semua attempt gagal
    save_failed "$msisdn" "$nama_paket" "$http_code" "$response_body"
    return 1
}

# Fungsi untuk menyimpan data gagal
save_failed() {
    local msisdn="$1"
    local nama_paket="$2"
    local http_code="$3"
    local response_body="$4"
    
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
    
    # Cek apakah sudah ada di failed.json
    local existing_count=$(jq --arg m "$msisdn" \
        '[.[] | select(.msisdn == $m)] | length' \
        "$FAILED_FILE" 2>/dev/null || echo "0")
    
    if [ "$existing_count" -eq 0 ]; then
        jq --arg msisdn "$msisdn" \
           --arg paket "$nama_paket" \
           --arg http_code "$http_code" \
           --arg response "$response_body" \
           '. += [{
               "msisdn": $msisdn, 
               "nama_paket": $paket,
               "http_code": $http_code,
               "response": $response,
               "timestamp": "'"$(date '+%Y-%m-%d %H:%M:%S')"'",
               "retry_count": 0
           }]' "$FAILED_FILE" > "${FAILED_FILE}.tmp" && \
        mv "${FAILED_FILE}.tmp" "$FAILED_FILE"
        
        log "Data gagal disimpan ke $FAILED_FILE"
    else
        log "WARNING: $msisdn sudah ada di failed.json, tidak disimpan duplikat"
    fi
}

# Fungsi untuk memproses file JSON dengan while loop yang aman
process_numbers() {
    local input_file="$1"
    local processing_mode="${2:-normal}"  # normal atau retry
    
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
    
    log "Memulai proses $total nomor dari $input_file (mode: $processing_mode)"
    
    # Buat file processing
    cp "$input_file" "$PROCESSING_FILE"
    
    processed_count=0
    success_count=0
    failed_count=0
    
    # FIX: Gunakan while loop untuk menghindari skip
    while [ $(jq 'length' "$PROCESSING_FILE" 2>/dev/null || echo "0") -gt 0 ]; do
        # Ambil data pertama
        msisdn=$(jq -r '.[0].msisdn' "$PROCESSING_FILE" 2>/dev/null)
        nama_paket=$(jq -r '.[0].nama_paket' "$PROCESSING_FILE" 2>/dev/null)
        
        # Validasi
        if [ -z "$msisdn" ] || [ "$msisdn" = "null" ]; then
            log "WARNING: MSISDN tidak valid, menghapus dari queue..."
            # Hapus element pertama
            jq '.[1:]' "$PROCESSING_FILE" > "${PROCESSING_FILE}.new" && \
                mv "${PROCESSING_FILE}.new" "$PROCESSING_FILE"
            continue
        fi
        
        processed_count=$((processed_count + 1))
        log "Progress: $processed_count/$total - MSISDN: $msisdn"
        
        # Eksekusi curl
        execute_curl "$msisdn" "$nama_paket"
        curl_exit_code=$?
        
        # Update counters
        if [ $curl_exit_code -eq 0 ]; then
            success_count=$((success_count + 1))
        else
            failed_count=$((failed_count + 1))
        fi
        
        # Hapus dari processing file (sudah diproses, sukses atau gagal)
        jq '.[1:]' "$PROCESSING_FILE" > "${PROCESSING_FILE}.new" && \
            mv "${PROCESSING_FILE}.new" "$PROCESSING_FILE"
        
        # Backup berkala setiap BATCH_SIZE request
        if [ $((processed_count % BATCH_SIZE)) -eq 0 ] && [ "$processing_mode" = "normal" ]; then
            log "Checkpoint: $processed_count nomor diproses (Success: $success_count, Failed: $failed_count)"
            
            # Buat backup state
            backup_file="${WORK_DIR}/backup_state_$(date +%Y%m%d_%H%M%S).json"
            jq -n \
                --arg processed "$processed_count" \
                --arg success "$success_count" \
                --arg failed "$failed_count" \
                --argfile remaining "$PROCESSING_FILE" \
                '{
                    processed: $processed,
                    success: $success,
                    failed: $failed,
                    remaining: $remaining,
                    timestamp: "'"$(date '+%Y-%m-%d %H:%M:%S')"'"
                }' > "$backup_file"
            
            log "Backup state dibuat: $backup_file"
            
            # Hapus backup lama (simpan hanya 3 terakhir)
            ls -t ${WORK_DIR}/backup_state_*.json 2>/dev/null | tail -n +4 | xargs rm -f 2>/dev/null
        fi
        
        # Delay untuk rate limiting sudah di handle di execute_curl
    done
    
    # Update file asli
    if [ "$processing_mode" = "normal" ]; then
        # Kosongkan file input karena semua sudah diproses
        echo "[]" > "$input_file"
    fi
    
    # Cleanup
    rm -f "$PROCESSING_FILE" "${PROCESSING_FILE}.new" 2>/dev/null
    
    log "Proses selesai. Success: $success_count, Failed: $failed_count"
    return 0
}

# Fungsi untuk retry nomor yang gagal dengan exponential backoff
retry_failed_numbers() {
    if [ ! -f "$FAILED_FILE" ] || [ ! -s "$FAILED_FILE" ]; then
        log "INFO: Tidak ada nomor gagal untuk di-retry"
        return 0
    fi
    
    local failed_count=$(jq 'length' "$FAILED_FILE")
    log "=== MULAI RETRY: $failed_count nomor gagal ==="
    
    # Filter hanya yang retry_count < MAX_RETRIES
    jq '[.[] | select(.retry_count < '"$MAX_RETRIES"')]' "$FAILED_FILE" > "${FAILED_FILE}.retry_candidates"
    
    local retry_candidates_count=$(jq 'length' "${FAILED_FILE}.retry_candidates")
    
    if [ "$retry_candidates_count" -eq 0 ]; then
        log "INFO: Tidak ada kandidat untuk retry (sudah mencapai max retries)"
        rm -f "${FAILED_FILE}.retry_candidates"
        return 0
    fi
    
    log "Kandidat retry: $retry_candidates_count dari $failed_count"
    
    # Ekstrak hanya msisdn dan nama_paket untuk retry
    jq '[.[] | {msisdn: .msisdn, nama_paket: .nama_paket}]' \
        "${FAILED_FILE}.retry_candidates" > "${WORK_DIR}/retry_batch.json"
    
    # Proses retry
    process_numbers "${WORK_DIR}/retry_batch.json" "retry"
    
    # Update failed.json dengan hasil retry
    if [ -f "${WORK_DIR}/retry_batch.json" ] && [ -s "${WORK_DIR}/retry_batch.json" ]; then
        local still_failed_count=$(jq 'length' "${WORK_DIR}/retry_batch.json")
        
        if [ "$still_failed_count" -gt 0 ]; then
            log "Masih ada $still_failed_count nomor gagal setelah retry"
            
            # Increment retry_count untuk yang masih gagal
            jq --slurpfile retry "${WORK_DIR}/retry_batch.json" '
                map(. as $item | 
                    if ($retry[0] | any(.msisdn == $item.msisdn)) then
                        .retry_count = (.retry_count + 1)
                    else
                        .
                    end
                )
            ' "$FAILED_FILE" > "${FAILED_FILE}.updated"
            
            mv "${FAILED_FILE}.updated" "$FAILED_FILE"
        else
            log "SEMUA RETRY BERHASIL!"
            # Hapus yang berhasil dari failed.json
            jq --slurpfile retry "${WORK_DIR}/retry_batch.json" '
                [.[] | select(
                    .msisdn as $m | 
                    ($retry[0] | any(.msisdn == $m) | not)
                )]
            ' "$FAILED_FILE" > "${FAILED_FILE}.updated"
            
            mv "${FAILED_FILE}.updated" "$FAILED_FILE"
        fi
        
        rm -f "${WORK_DIR}/retry_batch.json"
    else
        log "Semua retry berhasil atau tidak ada data retry"
        # Kosongkan failed.json karena semua berhasil
        echo "[]" > "$FAILED_FILE"
    fi
    
    # Cleanup
    rm -f "${FAILED_FILE}.retry_candidates" "${FAILED_FILE}.updated" 2>/dev/null
    
    log "=== RETRY SELESAI ==="
}

# Fungsi untuk validasi komplit setelah eksekusi
validate_completion() {
    local original_count="$1"
    
    log "=== VALIDASI FINAL ==="
    
    local success_count=$(jq 'length' "$SUCCESS_FILE" 2>/dev/null || echo "0")
    local failed_count=$(jq 'length' "$FAILED_FILE" 2>/dev/null || echo "0")
    local remaining_count=$(jq 'length' "$INPUT_FILE" 2>/dev/null || echo "0")
    
    local total_processed=$((success_count + failed_count))
    local discrepancy=$((original_count - total_processed))
    
    log "Original: $original_count"
    log "Success: $success_count"
    log "Failed: $failed_count"
    log "Remaining: $remaining_count"
    log "Total processed: $total_processed"
    
    if [ "$discrepancy" -ne 0 ]; then
        log "WARNING: Ada $discrepancy data yang tidak terproses"
        
        if [ "$remaining_count" -gt 0 ]; then
            log "Emergency: Memproses $remaining_count data yang tersisa..."
            
            # Emergency processing dengan delay lebih lama
            local emergency_delay=$((REQUEST_DELAY * 2))
            while [ $(jq 'length' "$INPUT_FILE") -gt 0 ]; do
                msisdn=$(jq -r '.[0].msisdn' "$INPUT_FILE")
                nama_paket=$(jq -r '.[0].nama_paket' "$INPUT_FILE")
                
                if [ -n "$msisdn" ] && [ "$msisdn" != "null" ]; then
                    log "Emergency processing: $msisdn"
                    execute_curl "$msisdn" "$nama_paket"
                fi
                
                # Hapus dari input
                jq '.[1:]' "$INPUT_FILE" > "${INPUT_FILE}.tmp" && \
                    mv "${INPUT_FILE}.tmp" "$INPUT_FILE"
                
                # Extra delay untuk emergency processing
                sleep "$emergency_delay"
            done
            
            log "Emergency processing selesai"
        fi
    else
        log "SUCCESS: Semua data berhasil diproses (100% complete)"
    fi
    
    log "=== VALIDASI SELESAI ==="
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
    init_json_file "$PROCESSING_FILE"
    
    # Validasi file input
    if ! validate_json_file "$INPUT_FILE"; then
        echo "ERROR: File $INPUT_FILE tidak berformat JSON valid"
        exit 1
    fi
    
    # Deduplikasi data sebelum proses
    deduplicate_and_validate
    
    # Inisialisasi file log
    echo "=== Log Eksekusi - Dimulai pada $(date) ===" > "$LOG_FILE"
    echo "Working Directory: $WORK_DIR" >> "$LOG_FILE"
    echo "Delay antar request: ${REQUEST_DELAY} detik" >> "$LOG_FILE"
    echo "Max retries: $MAX_RETRIES" >> "$LOG_FILE"
    echo "Batch size: $BATCH_SIZE" >> "$LOG_FILE"
    echo "==========================================" >> "$LOG_FILE"
    
    # Simpan count awal
    original_count=$(jq 'length' "$INPUT_FILE")
    log "Skrip dimulai"
    log "Jumlah data awal setelah deduplikasi: $original_count nomor"
    
    if [ "$original_count" -eq 0 ]; then
        log "Tidak ada data untuk diproses"
        echo "[]" > "$INPUT_FILE"
        exit 0
    fi
    
    # Perkiraan waktu selesai
    local estimated_seconds=$((original_count * REQUEST_DELAY))
    local estimated_minutes=$((estimated_seconds / 60))
    log "Perkiraan waktu selesai: $estimated_minutes menit"
    
    # Proses nomor utama
    log "=== PROSES UTAMA DIMULAI ==="
    process_numbers "$INPUT_FILE" "normal"
    
    # Tunggu sebelum retry
    log "Menunggu 10 detik sebelum retry..."
    sleep 10
    
    # Proses retry untuk nomor yang gagal
    log "=== PROSES RETRY DIMULAI ==="
    retry_failed_numbers
    
    # Validasi final
    validate_completion "$original_count"
    
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
cleanup() {
    log "Script diinterrupt. Menyimpan state..."
    
    # Simpan state ke recovery file
    if [ -f "$PROCESSING_FILE" ] && [ -s "$PROCESSING_FILE" ]; then
        recovery_file="${WORK_DIR}/recovery_$(date +%Y%m%d_%H%M%S).json"
        cp "$PROCESSING_FILE" "$recovery_file"
        log "Recovery file dibuat: $recovery_file"
        
        # Gabungkan kembali ke input file
        if [ -f "$INPUT_FILE" ]; then
            jq -s '.[0] + .[1]' "$INPUT_FILE" "$PROCESSING_FILE" > "${INPUT_FILE}.recovered"
            mv "${INPUT_FILE}.recovered" "$INPUT_FILE"
        else
            cp "$PROCESSING_FILE" "$INPUT_FILE"
        fi
    fi
    
    # Cleanup temporary files
    rm -f "$PROCESSING_FILE" "${PROCESSING_FILE}.new" 2>/dev/null
    
    log "State disimpan. Jalankan script lagi untuk melanjutkan."
    exit 1
}

trap cleanup INT TERM

# Jalankan main function
main "$@"
