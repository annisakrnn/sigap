/**
 * Canvas Digital Signature Pad Helper
 * SIM-GelarAlat
 * Mendukung layar sentuh ponsel (touch events) dan mouse desktop
 */

class SimpleSignaturePad {
    constructor(canvasElement, clearButton, hiddenInput) {
        this.canvas = canvasElement;
        this.clearBtn = clearButton;
        this.input = hiddenInput;
        this.ctx = this.canvas.getContext('2d');
        this.isDrawing = false;
        this.hasDrawn = false;
        
        this.init();
    }

    init() {
        this.resizeCanvas();
        window.addEventListener('resize', () => this.resizeCanvas());

        this.ctx.lineWidth = 2.5;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.strokeStyle = '#0f172a';

        // Mouse Events
        this.canvas.addEventListener('mousedown', (e) => this.startDrawing(e));
        this.canvas.addEventListener('mousemove', (e) => this.draw(e));
        this.canvas.addEventListener('mouseup', () => this.stopDrawing());
        this.canvas.addEventListener('mouseleave', () => this.stopDrawing());

        // Touch Events for Mobile
        this.canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            const touch = e.touches[0];
            this.startDrawing(touch);
        }, { passive: false });

        this.canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            const touch = e.touches[0];
            this.draw(touch);
        }, { passive: false });

        this.canvas.addEventListener('touchend', (e) => {
            e.preventDefault();
            this.stopDrawing();
        }, { passive: false });

        // Clear Button
        if (this.clearBtn) {
            this.clearBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.clear();
            });
        }
    }

    resizeCanvas() {
        const rect = this.canvas.getBoundingClientRect();
        // Simpan data jika sudah ada coretan
        let imgData = null;
        if (this.hasDrawn && this.canvas.width > 0 && this.canvas.height > 0) {
            imgData = this.canvas.toDataURL();
        }

        const dpr = window.devicePixelRatio || 1;
        this.canvas.width = rect.width * dpr;
        this.canvas.height = rect.height * dpr;
        this.ctx.scale(dpr, dpr);
        this.ctx.lineWidth = 2.5;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.strokeStyle = '#0f172a';

        if (imgData) {
            const img = new Image();
            img.onload = () => {
                this.ctx.drawImage(img, 0, 0, rect.width, rect.height);
            };
            img.src = imgData;
        }
    }

    getPos(e) {
        const rect = this.canvas.getBoundingClientRect();
        return {
            x: (e.clientX - rect.left),
            y: (e.clientY - rect.top)
        };
    }

    startDrawing(e) {
        this.isDrawing = true;
        const pos = this.getPos(e);
        this.ctx.beginPath();
        this.ctx.moveTo(pos.x, pos.y);
    }

    draw(e) {
        if (!this.isDrawing) return;
        const pos = this.getPos(e);
        this.ctx.lineTo(pos.x, pos.y);
        this.ctx.stroke();
        this.hasDrawn = true;
        this.updateInput();
    }

    stopDrawing() {
        if (this.isDrawing) {
            this.isDrawing = false;
            this.updateInput();
        }
    }

    clear() {
        const rect = this.canvas.getBoundingClientRect();
        this.ctx.clearRect(0, 0, rect.width, rect.height);
        this.hasDrawn = false;
        if (this.input) {
            this.input.value = '';
        }
    }

    updateInput() {
        if (this.input && this.hasDrawn) {
            this.input.value = this.canvas.toDataURL('image/png');
        }
    }

    isEmpty() {
        return !this.hasDrawn;
    }
}
