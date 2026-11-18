# vcp — AI-Powered Product Documentation Assistant

**vcp** adalah asisten AI berbasis web untuk menyusun dan merevisi dokumen produk secara instan: **Planning**, **Design**, dan **PRD (Product Requirements Document)** dalam format Markdown siap pakai dengan integrasi diagram alur (Mermaid.js) dan live preview.

---

## Fitur Utama

- **3 Tipe Dokumen Produk**:
  - **Planning**: Roadmap, milestone, task breakdown, timeline, dan tabel analisis risiko.
  - **Design**: Design system, tokens (warna/tipografi/spacing), komponen, dan analisis referensi visual UI.
  - **PRD**: Spesifikasi kebutuhan, user stories, flowchart alur (Mermaid TD), dan ERD (Mermaid erDiagram).
- **Interactive AI Chat & Live Preview**:
  - Kolaborasi chat dengan AI di satu panel, langsung melihat hasil dokumen Markdown di panel preview secara real-time.
  - Editor Markdown manual dengan debounced auto-save.
  - Wizard interaktif untuk mengarahkan platform & stack teknologi sebelum menulis PRD.
- **Ekspor Cepat**: Salin Markdown ke clipboard atau unduh sebagai file `.md`.
- **Desain Responsif**: Nyaman digunakan di ponsel, tablet, maupun layar desktop lebar (mendukung dynamic viewport `100dvh` dan safe-area insets).
- **Autentikasi Fleksibel**: Login email/password dan OAuth satu klik via Google & GitHub.
- **Konfigurasi Berbasis `.env`**: Seluruh kredensial database, API key AI, dan OAuth secret tersimpan aman.

---

## Tech Stack

- **Backend**: PHP 8.1+ (PDO MySQL, cURL)
- **Database**: MySQL / MariaDB
- **Frontend**: Vanilla JavaScript (ES6+), Tailwind CSS (Typography Plugin), Marked.js, DOMPurify, Mermaid.js
- **AI Engine**: Kompatibel dengan OpenAI Chat Completions API (OpenAI, NVIDIA NIM, Groq, OpenRouter, dll.)

---

## Panduan Instalasi Lokal

### 1. Kloning Repository
```bash
git clone https://github.com/vjdevelopervj/vcp.git
cd vcp
```

### 2. Konfigurasi Environment (`.env`)
Salin file `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Buka file `.env` dan sesuaikan nilainya:
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `AI_API_KEY`, `AI_BASE_URL`, `AI_MODEL` *(kosongkan AI_API_KEY jika ingin menjalankan demo mode)*
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` *(opsional)*
- `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` *(opsional)*

### 3. Setup Database
Import skema tabel ke MySQL:
```bash
mysql -u root -p vcp < database.sql
```

### 4. Jalankan Aplikasi
Jika menggunakan Laragon / XAMPP / Apache:
- Letakkan di folder `www/vcp` atau `htdocs/vcp`.
- Akses melalui `http://localhost/vcp` atau `http://vcp.test`.

---

## Lisensi
Distributed under the MIT License.
