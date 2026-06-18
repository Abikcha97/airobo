# TEXNIK TOPSHIRIQ
## Yangi xizmat: "Kartadan Naqd Pul Yechish"
### ELPAY To'lov Tizimi — Web + Android POS Terminal

**Versiya:** 1.0  
**Sana:** 2026-yil iyun  
**Manzil:** Frontend (Web) va Front WebDev + Backend muhandislari  

---

## 1. LOYIHA KONTEKSTI

ELPAY to'lov tizimida yangi xizmat — **"Kartadan Naqd Pul Yechish"** — qo'shilmoqda.

### Mavjud platforma:
- Kompyuter uchun **Web versiya** (operator-kassir interfeysi)
- **Android POS terminal** ilovasi

### Agentlik tarmog'i (moliyaviy kontekst):
- 50+ to'lov agentlari (MCHJ / YaTT shaklida)
- Har bir agent ELPAY tizimida **depozit hisobraqami** ochgan
- Agent depozitini **bank hisobraqamidan ELPAY MCHJ ga** pul ko'chirish orqali to'ldiradi

### Avvalgi to'lov xizmatlarida pul oqimi:
```
Mijoz → naqd pul → Agentga beradi
Agent → depozitidan → to'lov qiladi (depozit kamayadi)
```

### Yangi xizmatda pul oqimi (TESKARI):
```
Mijoz → karta raqami → Tranzaksiya → kartadan pul yechiladi
Yechilgan pul → Agent depozitiga KIRIM bo'ladi
Agent → mijozga → naqd pul beradi
```

---

## 2. XIZMAT TAVSIFI

### Nom: Kartadan Naqd Pul Yechish  
### Inglizcha: Card Cash Out / Cash Withdrawal from Card

### Asosiy jarayon (step-by-step):

| # | Qadam | Kim bajaradi | Kanal |
|---|-------|--------------|-------|
| 1 | Xizmat tanlanadi | Operator-kassir | Web / POS |
| 2 | Mijoz karta raqami kiritiladi | Kassir yoki Mijoz | Web / POS |
| 3 | Kartaning amal qilish muddati kiritiladi | Kassir yoki Mijoz | Web / POS |
| 4 | SMS OTP karta ulangan telefonga yuboriladi | Tizim (avtomatik) | SMS Gateway |
| 5 | Mijoz SMS OTP kodni kiritadi | Kassir (mijozdan olib) | Web / POS |
| 6 | Naqdlashtirilmoqchi bo'lgan summa kiritiladi | Kassir yoki Mijoz | Web / POS |
| 7 | Komissiya hisob-kitob qilinib ko'rsatiladi | Tizim (avtomatik) | Web / POS |
| 8 | "To'lash" tugmasi bosiladi | Kassir | Web / POS |
| 9 | Tranzaksiya amalga oshiriladi | Tizim (processing) | Backend |
| 10 | Kartadan pul yechiladi, agent depozitiga kirim | Tizim (avtomatik) | Backend |
| 11 | Chek chiqariladi | Tizim (avtomatik) | Web / POS Printer |
| 12 | Kassir mijozga naqd pul beradi | Kassir | Jismoniy |

---

## 3. FUNKSIONAL TALABLAR

### 3.1 Frontend (Web — Operator/Kassir Interfeysi)

#### Yangi sahifa / modal: "Kartadan Naqd Pul Yechish"

**Forma maydonlari:**

| Maydon | Turi | Validatsiya | Izoh |
|--------|------|-------------|------|
| Karta raqami | `input[type=text]` | 16 raqam, Luhn tekshiruvi | Mask: `0000 0000 0000 0000` |
| Amal qilish muddati | `input[type=text]` | MM/YY formati, o'tgan sana xato | Mask: `MM/YY` |
| SMS OTP | `input[type=number]` | 4–6 raqam | Taymer bilan (60 sek) |
| Summa | `input[type=number]` | Min/Max chegaralar, butun son | Agent limitiga qarab |
| Komissiya (avto) | `readonly` | — | Backend hisobi |
| Jami yechiladi | `readonly` | — | Summa + Komissiya |

**Tugmalar:**
- `OTP Yuborish` — SMS jo'natish (debounce: 60 sek qayta yuborish taqiqi)
- `Tranzaksiyani amalga oshirish` — barcha validatsiyalar o'tgandan keyin faol
- `Bekor qilish` — formani tozalash

**Holat ko'rsatkichlari (Status indicators):**
- `Karta tekshirilmoqda...` — spinner
- `OTP yuborildi` — muvaffaqiyat xabari + taymer
- `Tranzaksiya amalga oshirildi ✓` — yashil rang, chek ma'lumotlari
- `Xato: [xato matni]` — qizil rang, aniq sabab

---

### 3.2 Frontend (Android POS Terminal)

Xuddi Web versiya bilan bir xil jarayon, **POS terminal UI/UX** ga moslashtirilgan holda:

- Katta shrift, tegish uchun qulay tugmalar (min 48dp)
- Karta raqamini qo'lda kiritish yoki **karta o'qitgich (card reader)** orqali avtomatik to'ldirish
- OTP maydon — raqamli klaviatura
- Chek — termal printer orqali chiqarish
- Offline rejimda ishlamasligi (har doim online)

---

### 3.3 Backend / API

#### Yangi API Endpoint'lar:

```
POST /api/v1/cashout/initiate
  Body: { card_number, expiry_date, agent_id }
  Response: { session_id, masked_phone, otp_sent: true }

POST /api/v1/cashout/verify-otp
  Body: { session_id, otp_code }
  Response: { verified: true, card_holder_name, available_limit }

POST /api/v1/cashout/execute
  Body: { session_id, amount, agent_id, operator_id }
  Response: { transaction_id, status, receipt_data, agent_balance_after }

GET /api/v1/cashout/receipt/{transaction_id}
  Response: { PDF/JSON chek ma'lumotlari }
```

#### Biznes logika (Backend):

1. **Karta tekshiruvi** — processing markazi bilan integratsiya (mavjud)
2. **OTP generatsiya va yuborish** — SMS Gateway orqali
3. **Limit tekshiruvi:**
   - Kartada yetarli mablag' bormi?
   - Agent depozitida yetarli naqd mablag' bormi? *(agent mijozga naqd pul berishi kerak)*
   - Kunlik / oylik limit (compliance bo'yicha)
4. **Tranzaksiya bajarish:**
   - Kartadan `summa + 1% komissiya` yechish (OSON orqali)
   - OSON 1% komissiyani o'zida ushlab qoladi (0.6% — sof daromadi)
   - Agent depozitiga faqat **asosiy summa** kirim qilinadi
   - OSON ning 0.4% ELPAY ga mukofoti va ELPAY ning 0.2% Agent mukofoti — **oylik hisob-kitobda** to'lanadi
5. **Chek generatsiya**
6. **Audit log yozish**

---

## 4. MOLIYAVIY HISOB-KITOB (FINANCE FLOW)

### 4.1 Ishtirokchilar tuzilmasi

Ushbu xizmatda **3 darajali** agentlik tizimi mavjud:

```
OSON (Processing markazi va Komissiya egasi)
  └── ELPAY MCHJ  (OSON ning agenti)
        └── ELPAY Agentlari  (ELPAY ning agentlari — kassirlar)
```

> ELPAY ham o'zi OSON uchun **agent** hisoblanadi. Shuning uchun OSON 1% komissiyani yig'ib, ELPAY ga o'z mukofotini (0.4%) qaytaradi. ELPAY esa bundan o'z agentlariga (0.2%) mukofot beradi.

---

### 4.2 Tranzaksiya vaqtidagi real-time pul oqimi

```
┌──────────────┐
│  MIJOZ KARTA │  summa + 1% komissiya
│              │  (masalan: 50 000 + 500 = 50 500 so'm)
└──────┬───────┘
       │
       ▼
┌──────────────────────────────────────────────┐
│           OSON PROCESSING                    │
│  Barcha 1% komissiya (500 so'm) OSON ga kirim│
│  OSON 0.6% o'zida ushlab qoladi              │
│  OSON ELPAYga 0.4% mukofot qaytaradi (oylik) │
└──────────────────┬───────────────────────────┘
                   │
         asosiy summa (50 000 so'm)
                   │
                   ▼
┌──────────────────────────────────────────────┐
│           ELPAY MCHJ HISOBI                  │
│  Asosiy summa tranzit orqali agentga o'tadi  │
└──────────────────┬───────────────────────────┘
                   │
         50 000 so'm (asosiy summa)
                   │
                   ▼
┌──────────────────────────────────────────────┐
│           ELPAY AGENT DEPOZITI               │
│  (+50 000 so'm kirim bo'ladi)                │
└──────────────────┬───────────────────────────┘
                   │ naqd pul beradi
                   ▼
            ┌─────────────┐
            │    MIJOZ    │
            │ 50 000 so'm │
            │ naqd oladi  │
            └─────────────┘
```

---

### 4.3 Komissiya taqsimoti (1% = 500 so'm, 50 000 so'm misoli)

| Taraf | Foiz | Summa | Qachon | Izoh |
|-------|------|-------|--------|------|
| **Mijoz** to'laydi | **1.0%** | 500 so'm | Tranzaksiya vaqtida | Kartadan yechiladi |
| **OSON** ushlab qoladi | **0.6%** | 300 so'm | Tranzaksiya vaqtida | OSON ning sof daromadi |
| **OSON → ELPAY** mukofot | **0.4%** | 200 so'm | Oylik hisob-kitobda | ELPAY uchun mukofot (OSON ning agenti sifatida) |
| **ELPAY → Agent** mukofot | **0.2%** | 100 so'm | Oylik hisob-kitobda | ELPAY agentiga mukofot |
| **ELPAY** sof daromadi | **0.2%** | 100 so'm | Oylik hisob-kitobdan keyin | 0.4% − 0.2% = 0.2% |

---

### 4.4 Oylik mukofot qaytarish oqimi

```
OSON  ──(0.4% mukofot)──►  ELPAY MCHJ HISOBI
                                    │
                          ┌─────────┴──────────┐
                          │                    │
                  0.2% agent mukofoti    0.2% ELPAY sof daromadi
                          │                    │
                          ▼                    ▼
                  AGENT DEPOZITI        ELPAY MCHJ
                  (mukofot kirim)       (sof foyda)
```

---

### 4.5 To'liq hisoblash misollari

| Tranzaksiya summasi | Mijoz to'laydi (1%) | OSON ushlab qoladi (0.6%) | ELPAY ga keladi (0.4%) | ELPAY agentiga (0.2%) | ELPAY sof daromadi (0.2%) |
|---------------------|---------------------|---------------------------|------------------------|-----------------------|---------------------------|
| 50 000 so'm | **500 so'm** | 300 so'm | 200 so'm | **100 so'm** | **100 so'm** |
| 500 000 so'm | **5 000 so'm** | 3 000 so'm | 2 000 so'm | **1 000 so'm** | **1 000 so'm** |
| 1 000 000 so'm | **10 000 so'm** | 6 000 so'm | 4 000 so'm | **2 000 so'm** | **2 000 so'm** |
| 5 000 000 so'm | **50 000 so'm** | 30 000 so'm | 20 000 so'm | **10 000 so'm** | **10 000 so'm** |
| 10 000 000 so'm | **100 000 so'm** | 60 000 so'm | 40 000 so'm | **20 000 so'm** | **20 000 so'm** |

> **Eslatma:** OSON → ELPAY va ELPAY → Agent mukofotlari **tranzaksiya vaqtida emas**, oylik hisob-faktura yopilganda to'lanadi.

---

## 5. HISOB-FAKTURA VA DALOLATNOMALAR

### 5.1 Yangi hujjat turlari

Ushbu xizmat uchun maxsus hisob-faktura va solishtirma dalolatnomalar tizimi zarur:

#### a) Chek (Mijozga)
Har bir tranzaksiyadan keyin avtomatik:
```
ELPAY To'lov Tizimi
─────────────────────────────
Xizmat:      Kartadan Naqd Pul Yechish
Sana/Vaqt:   DD.MM.YYYY HH:MM
Tranzaksiya: #XXXXXXXXXXXXXXXX
─────────────────────────────
Karta:       **** **** **** 1234
Summa:       500,000 so'm
Komissiya:   7,500 so'm
Jami yechildi: 507,500 so'm
─────────────────────────────
Kassir:      Ism Familiya
Filial:      Agent nomi
─────────────────────────────
Chek raqami: XXXXXXXXXX
```

#### b) Agent uchun Kirim Hisob-Fakturasi
Har bir tranzaksiya bo'yicha:
- Tranzaksiya ID
- Sana va vaqt
- Yechilgan summa (mijoz kartasidan)
- Agent depozitiga kirim summasi
- Kassir ID
- Karta (mask qilingan)

#### c) Oylik Solishtirma Dalolatnoma (Agent — ELPAY)
Har oy oxirida avtomatik generatsiya:

| Ustun | Ma'lumot |
|-------|----------|
| Davr | Oy/Yil |
| Agent nomi | MCHJ/YaTT nomi |
| Jami tranzaksiya soni | — |
| Jami yechilgan summa | — |
| Jami komissiya summasi | — |
| Jami agent depozitiga kirim | — |
| Boshlang'ich depozit qoldig'i | — |
| Yakuniy depozit qoldig'i | — |
| Imzo va muhr joyi | — |

---

## 6. XAVFSIZLIK TALABLARI

| Talab | Tavsif |
|-------|--------|
| OTP muddati | 60 soniyadan ko'p bo'lmasin |
| OTP urinishlar | Maks 3 marta, undan keyin sessiya bloklanadi |
| Sessiya muddati | Initiate dan Execute gacha maks 5 daqiqa |
| Karta ma'lumotlari | Backend'da **PCI DSS** standartiga muvofiq saqlanadi, frontend'da faqat mask |
| TLS/HTTPS | Barcha API muloqotlari shifrlangan |
| Audit log | Har bir harakat (initiate, OTP send, verify, execute, fail) logga yoziladi |
| Operator autentifikatsiya | Kassir tizimga kirishi PIN/parol bilan tasdiqlangan bo'lishi shart |
| Double-spend himoya | Bir sessiya faqat bir marta execute bo'lishi mumkin |

---

## 7. XATO HOLATLARI VA UX

| Xato | Xabar (foydalanuvchiga) | Backend status |
|------|------------------------|----------------|
| Noto'g'ri karta raqami | "Karta raqami noto'g'ri kiritilgan" | 400 |
| Karta muddati o'tgan | "Kartaning amal qilish muddati tugagan" | 400 |
| Kartada mablag' yetarli emas | "Kartada yetarli mablag' mavjud emas" | 402 |
| Noto'g'ri OTP | "SMS kod noto'g'ri. X ta urinish qoldi" | 401 |
| OTP muddati o'tgan | "Kod muddati tugadi. Qayta yuboring" | 410 |
| Agent depoziti yetarli emas | "Xizmat vaqtincha mavjud emas. Administratorga murojaat qiling" | 503 |
| Processing xatosi | "Tranzaksiya amalga oshmadi. Qayta urinib ko'ring" | 500 |
| Limit oshib ketdi | "Kunlik limit oshib ketdi" | 429 |
| Network xato | "Internetga ulanishni tekshiring" | Network |

---

## 8. TEXNIK ROADMAP

### Bosqich 1: Tahlil va Dizayn (1–2 hafta)
- [ ] Mavjud to'lov xizmatlari arxitekturasi bilan tanishish
- [ ] Processing markazi bilan integratsiya hujjatlarini ko'rish
- [ ] SMS Gateway API hujjatlarini ko'rish
- [ ] UI/UX prototip (Figma) tayyorlash — Web va POS uchun
- [ ] API kontraktini belgilash (swagger/openapi)
- [ ] Moliyaviy oqim sxemasini moliyachilar bilan tasdiqlash
- [ ] PCI DSS talablarini baholash

### Bosqich 2: Backend Ishlanmalar (2–3 hafta)
- [ ] Yangi database jadvallari: `cashout_transactions`, `cashout_sessions`
- [ ] `POST /cashout/initiate` — karta tekshiruvi, OTP yuborish
- [ ] `POST /cashout/verify-otp` — OTP tekshiruvi
- [ ] `POST /cashout/execute` — tranzaksiyani bajarish, depozit kirim
- [ ] Komissiya hisoblash engine
- [ ] Audit log moduli
- [ ] Chek generatsiya (PDF)
- [ ] Agent hisobi kirim logikasi
- [ ] Unit testlar

### Bosqich 3: Frontend Ishlanmalar — Web (1–2 hafta)
- [ ] Yangi "Kartadan Naqd Yechish" moduli/sahifasi
- [ ] Karta raqami input mask (16 raqam + Luhn)
- [ ] OTP forma + taymer (60 sek countdown)
- [ ] Summa va komissiya avtohisob ko'rsatkichi
- [ ] Tranzaksiya holati animatsiyalari (loading, success, error)
- [ ] Chek ko'rsatish va print funksiyasi
- [ ] Responsive dizayn

### Bosqich 4: Frontend Ishlanmalar — Android POS (1–2 hafta)
- [ ] POS terminal uchun yangi Activity/Fragment
- [ ] Card reader integratsiyasi (agar mavjud bo'lsa)
- [ ] Katta shrift, qulay tugmalar (accessibility)
- [ ] Termal printer integratsiyasi (chek chiqarish)
- [ ] Offline holatda xato ko'rsatish
- [ ] POS-ga xos UX optimallashtirish

### Bosqich 5: Hisob-Faktura va Dalolatnoma Moduli (1 hafta)
- [ ] Agent kirim hisob-fakturasi generatsiyasi
- [ ] Oylik solishtirma dalolatnoma (PDF/Excel)
- [ ] Admin panel: agent bo'yicha cashout hisobotlari
- [ ] Filtr: sana, agent, tranzaksiya holati

### Bosqich 6: Test va Integratsiya (1–2 hafta)
- [ ] Staging muhitida end-to-end test
- [ ] Load testing (bir vaqtda ko'p tranzaksiya)
- [ ] Xavfsizlik tekshiruvi (OTP brute-force, double-spend)
- [ ] Moliyaviy hisob tekshiruvi (balans moslig'i)
- [ ] POS terminal qurilmalarida test
- [ ] UAT (foydalanuvchi qabul testi) — 2–3 agent bilan pilot

### Bosqich 7: Ishga Tushirish (1 hafta)
- [ ] Production deployment
- [ ] Monitoring va alertlar sozlash
- [ ] Kassirlar uchun qo'llanma/instruksiya
- [ ] Pilot agentlar bilan soft launch
- [ ] Muammolarni kuzatish va tezkor tuzatish
- [ ] To'liq rollout

---

## 9. UMUMIY BAHOLASH

| Bosqich | Muddat (taxminiy) | Javobgar |
|---------|-------------------|---------|
| Tahlil va Dizayn | 1–2 hafta | PM + UX + Backend Lead |
| Backend | 2–3 hafta | Backend dasturchilari |
| Frontend Web | 1–2 hafta | Web Frontend dasturchi |
| Frontend Android POS | 1–2 hafta | Android dasturchi |
| Hisob-Faktura moduli | 1 hafta | Backend + Frontend |
| Test va Integratsiya | 1–2 hafta | QA + Barcha |
| Ishga tushirish | 1 hafta | DevOps + PM |
| **JAMI** | **8–13 hafta** | |

---

## 10. QABUL MEZONLARI (Definition of Done)

Xizmat tugallangan hisoblanadi, quyidagilar bajarilganda:

- [ ] Mijoz karta ma'lumotlarini kiritib, OTP orqali tasdiqlaydi
- [ ] Tranzaksiya muvaffaqiyatli bajariladi va kartadan pul yechiladi
- [ ] Agent depozitiga to'g'ri summa kirim bo'ladi
- [ ] Kassirga va mijozga chek beriladi
- [ ] Barcha xato holatlari to'g'ri ko'rsatiladi
- [ ] Web va POS terminal ikkalasida ishlaydi
- [ ] Audit log to'liq yoziladi
- [ ] Oylik dalolatnoma generatsiyasi ishlaydi
- [ ] Load test: kamida 50 bir vaqtdagi tranzaksiyani ko'tara oladi
- [ ] Xavfsizlik tekshiruvidan o'tgan

---

## 11. ANIQLANГАН PARAMETRLAR

Quyidagi masalalar biznes/moliya taraf bilan kelishilgan va tasdiqlangan:

| # | Masala | Qaror |
|---|--------|-------|
| 1 | **Mijoz komissiyasi** | **1%** (tranzaksiya summasidan, OSON oladi) |
| 2 | **ELPAY ga qaytariladigan mukofot (OSON dan)** | **0.4%** (oylik hisob-kitobda) |
| 3 | **ELPAY agentiga mukofot** | **0.2%** (oylik hisob-kitobda) |
| 4 | **ELPAY sof daromadi** | **0.2%** (0.4% − 0.2%) |
| 5 | **OSON sof daromadi** | **0.6%** (1% − 0.4%) |
| 6 | **Kunlik limit — mijoz uchun** | **30 000 000 so'm** |
| 7 | **Kunlik/oylik limit — agent uchun** | **Cheksiz** |
| 8 | **Processing markazi** | **OSON to'lov tizimi** — integratsiya va API hujjatlari mavjud |
| 9 | **SMS Gateway / OTP** | **OSON integrator** tomonidan hal qilinadi |
| 10 | **Qo'llab-quvvatlanadigan karta turlari** | **UzCard, Humo, UzCard-Visa** |
| 11 | **Minimal yechish summasi** | **50 000 so'm** |
| 12 | **Maksimal yechish summasi** | **10 000 000 so'm** |
| 13 | **Agent depoziti yetarli bo'lmaganda** | Tranzaksiya **rad etiladi** |
| 14 | **Termal chek kengligi** | **57mm va 80mm** — ikkalasi ham qo'llab-quvvatlanadi |
| 15 | **Dalolatnomani imzolash** | **Elektron imzo** |

---

## 12. AGENT MUKOFOT PULI (VOZNAGRAJDENIYE)

### 12.1 Umumiy tushuncha — 3 darajali agentlik tizimi

```
OSON  (1% komissiya yig'adi, 0.6% o'zida qoladi, 0.4% ELPAYga qaytaradi)
  └── ELPAY MCHJ  (0.4% oladi, 0.2% agentiga beradi, 0.2% o'zida qoladi)
        └── ELPAY Agenti  (0.2% mukofot oladi — oylik)
```

Mukofot pullar **tranzaksiya vaqtida emas**, **har oyning oxirida** hisob-faktura yopilganda to'lanadi.

---

### 12.2 Oylik mukofot to'lash jarayoni

**1-qadam:** OSON oyni yopadi → ELPAY ga 0.4% mukofot to'laydi  
**2-qadam:** ELPAY oyni yopadi → Har bir agentga 0.2% mukofot depozitiga kirim qiladi  
**3-qadam:** ELPAY da 0.2% sof daromad qoladi

---

### 12.3 Mukofot hisoblash misoli (50 000 so'm tranzaksiya)

| Taraf | Foiz | Summa | Vaqt | Izoh |
|-------|------|-------|------|------|
| **Mijoz** to'laydi | 1.0% | **500 so'm** | Tranzaksiya vaqtida | Kartadan yechiladi |
| **OSON** ushlab qoladi | 0.6% | **300 so'm** | Tranzaksiya vaqtida | OSON sof daromadi |
| **OSON → ELPAY** mukofot | 0.4% | **200 so'm** | Oylik hisob-kitobda | ELPAY ning OSON dagi mukofoti |
| **ELPAY → Agent** mukofot | 0.2% | **100 so'm** | Oylik hisob-kitobda | Agent depozitiga kirim |
| **ELPAY** sof daromadi | 0.2% | **100 so'm** | Oylik yopilishdan so'ng | ELPAY MCHJ sof foyda |

---

### 12.4 Katta summalar uchun hisoblash jadvali

| Tranzaksiya summasi | Mijoz (1%) | OSON (0.6%) | ELPAY ga (0.4%) | Agent mukofoti (0.2%) | ELPAY sof (0.2%) |
|---------------------|------------|-------------|-----------------|-----------------------|------------------|
| 50 000 so'm | **500** | 300 | 200 | **100 so'm** | **100 so'm** |
| 500 000 so'm | **5 000** | 3 000 | 2 000 | **1 000 so'm** | **1 000 so'm** |
| 1 000 000 so'm | **10 000** | 6 000 | 4 000 | **2 000 so'm** | **2 000 so'm** |
| 5 000 000 so'm | **50 000** | 30 000 | 20 000 | **10 000 so'm** | **10 000 so'm** |
| 10 000 000 so'm | **100 000** | 60 000 | 40 000 | **20 000 so'm** | **20 000 so'm** |

---

### 12.5 Oylik hisob-faktura tuzilmasi

Har oyning oxirida tizim **ikki xil hisob-faktura** generatsiya qiladi:

#### A) OSON → ELPAY hisob-fakturasi
```
Oylik mukofot = Σ (har bir tranzaksiya summasi × 0.4%)
```

| Ustun | Ma'lumot |
|-------|----------|
| Davr | O'tgan oy |
| Jami tranzaksiya soni | — |
| Jami naqdlashtirilgan summa | — |
| Jami komissiya (1%) | — |
| ELPAY ga mukofot (0.4%) | — |
| OSON sof daromad (0.6%) | — |

#### B) ELPAY → Har bir Agent hisob-fakturasi
```
Agent oylik mukofoti = Σ (ushbu agent tranzaksiyalari summasi × 0.2%)
```

| Ustun | Ma'lumot |
|-------|----------|
| Davr | O'tgan oy |
| Agent nomi | MCHJ/YaTT nomi |
| Jami tranzaksiya soni | — |
| Jami naqdlashtirilgan summa | — |
| Agent mukofoti (0.2%) | — |
| To'lov sanasi | Oylik yopilish sanasi |
| To'lov holati | To'langan / Kutilmoqda |

---

### 12.6 Backend talablari — Mukofot moduli

- [ ] Har bir tranzaksiyada quyidagilarni alohida jadvalga yozish:
  - `oson_share = amount × 0.006` (OSON sof daromadi)
  - `elpay_reward_from_oson = amount × 0.004` (ELPAY ga keladi)
  - `agent_reward = amount × 0.002` (agentga qaytariladi)
  - `elpay_net_income = amount × 0.002` (ELPAY sof daromadi)
- [ ] Oylik yopilish jarayoni (month-end closing job):
  1. OSON dan ELPAY ga 0.4% mukofot kirim qilish
  2. ELPAY dan har bir agentga 0.2% mukofot depozitga kirim qilish
- [ ] Mukofot tranzaksiyalari `cashout_rewards` jadvalida saqlanishi
- [ ] Admin panelda: OSON → ELPAY va ELPAY → Agent bo'yicha hisobotlar
- [ ] Oylik hisob-faktura PDF/Excel generatsiyasi (ikkala tur uchun)

---

### 12.7 Muhim qoidalar

> ⚠️ **Tranzaksiya vaqtida:** Faqat asosiy summa agent depozitiga kirim bo'ladi. Mukofot pul hali to'lanmaydi.  
> ✅ **Oylik yopilishda:** OSON → ELPAY (0.4%), keyin ELPAY → Agent (0.2%) ketma-ket to'lanadi.  
> 📊 Har bir tranzaksiyaning mukofot ulushi real-time hisoblanib, `cashout_rewards` jadvalida saqlanadi.  
> 🔗 ELPAY ham OSON uchun **agent** hisoblanadi — shuning uchun ikkala darajadagi mukofot tizimi parallel ishlaydi.

---

*Hujjat tayyorlagan: ELPAY loyiha jamoasi*  
*Versiya: 1.0 | Sana: 2026-yil iyun*  
*Keyingi yangilanish: Bosqich 1 xulosasidan keyin*
