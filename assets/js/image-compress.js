/**
 * SIGAP Client-Side Image Compression
 * Mengurangi ukuran foto kamera HP (5MB-12MB -> 300KB-600KB)
 * Menggunakan HTML5 Canvas sebelum upload agar proses submit instan & hemat kuota
 */

window.ImageCompressor = {
    /**
     * Kompres single file gambar
     * @param {File} file 
     * @param {Object} options { maxDimension: 1400, quality: 0.8 }
     * @returns {Promise<File>}
     */
    compress: function(file, options = {}) {
        const maxDimension = options.maxDimension || 1400;
        const quality = options.quality !== undefined ? options.quality : 0.8;

        // Hanya proses file gambar
        if (!file.type.match(/image.*/)) {
            return Promise.resolve(file);
        }

        // Jika ukuran sudah kecil (< 350KB), tidak perlu dikompres
        if (file.size < 350 * 1024) {
            return Promise.resolve(file);
        }

        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = function(event) {
                const img = new Image();
                img.src = event.target.result;
                img.onload = function() {
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > maxDimension) {
                            height = Math.round((height * maxDimension) / width);
                            width = maxDimension;
                        }
                    } else {
                        if (height > maxDimension) {
                            width = Math.round((width * maxDimension) / height);
                            height = maxDimension;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(function(blob) {
                        if (!blob) {
                            resolve(file);
                            return;
                        }
                        const newName = file.name.replace(/\.[^/.]+$/, "") + ".jpg";
                        const compressedFile = new File([blob], newName, {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        resolve(compressedFile);
                    }, 'image/jpeg', quality);
                };
                img.onerror = function() {
                    resolve(file); // Fallback ke file asli jika gagal render
                };
            };
            reader.onerror = function() {
                resolve(file);
            };
        });
    },

    /**
     * Attach auto-compress handler ke input file
     * @param {HTMLInputElement} inputEl
     * @param {Function} onProgress
     */
    attach: function(inputEl, onProgress) {
        if (!inputEl) return;
        inputEl.addEventListener('change', async function(e) {
            const file = inputEl.files[0];
            if (!file) return;

            if (onProgress) onProgress('compressing', file);

            try {
                const compressed = await window.ImageCompressor.compress(file);
                // Ganti file di file input menggunakan DataTransfer
                const dt = new DataTransfer();
                dt.items.add(compressed);
                inputEl.files = dt.files;

                if (onProgress) onProgress('done', compressed, file);
            } catch(err) {
                if (onProgress) onProgress('error', file);
            }
        });
    }
};
