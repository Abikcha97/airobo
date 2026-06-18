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
   - Kartadan `summa + komissiya` yechish
   - Agent depozitiga `summa` kirim qilish (komissiya ELPAY da qoladi)
5. **Chek generatsiya**
6. **Audit log yozish**

---

## 4. MOLIYAVIY HISOB-KITOB (FINANCE FLOW)

### 4.1 Pul oqimi sxemasi

```
┌─────────────┐     summa + komissiya    ┌──────────────────┐
│  MIJOZ KARTA │ ───────────────────────► │  ELPAY PROCESSING │
└─────────────┘                          └──────────┬───────┘
                                                    │
                                      summa (komissiyasiz)
                                                    │
                                                    ▼
┌──────────────────────────────────────────────────────────┐
│              ELPAY MCHJ HISOB RAQAMI                     │
│  (komissiya bu yerda qoladi — ELPAY daromadi)            │
└──────────────────────────────────────────────────────────┘
                                                    │
                                              summa kirim
                                                    │
                                                    ▼
┌─────────────────────┐    naqd pul beradi  ┌──────────────┐
│  AGENT DEPOZITI     │ ◄─────────────────  │              │
│  (depozit ko'payadi)│                     │   KASSIR /   │
└─────────────────────┘                     │    AGENT     │
                                            └──────┬───────┘
                                                   │ naqd pul
                                                   ▼
                                           ┌──────────────┐
                                           │    MIJOZ     │
                                           └──────────────┘
```

### 4.2 Komissiya tuzilmasi

*(Backend va Biznes taraf birgalikda aniqlaydi)*

| Parametr | Tavsif |
|----------|--------|
| Komissiya % | Tranzaksiya summasidan foiz (masalan: 1.5%) |
| Minimal komissiya | Masalan: 5,000 so'm |
| Maksimal komissiya | Masalan: 50,000 so'm |
| Kim to'laydi | Mijoz (kartadan yechiladi: summa + komissiya) |
| Kim oladi | ELPAY MCHJ |
| Agentga ta'sir | Faqat summa kirim, komissiya emas |

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
| 1 | **Komissiya foizi** | **1%** (tranzaksiya summasidan) |
| 2 | **Kunlik limit — mijoz uchun** | **30 000 000 so'm** |
| 3 | **Kunlik/oylik limit — agent uchun** | **Cheksiz** |
| 4 | **Processing markazi** | **OSON to'lov tizimi** — integratsiya va API hujjatlari mavjud |
| 5 | **SMS Gateway / OTP** | **OSON integrator** tomonidan hal qilinadi |
| 6 | **OTP yuborish narxi** | **OSON integrator** tomonidan hal qilinadi |
| 7 | **Qo'llab-quvvatlanadigan karta turlari** | **UzCard, Humo, UzCard-Visa** |
| 8 | **Minimal yechish summasi** | **50 000 so'm** |
| 9 | **Maksimal yechish summasi** | **10 000 000 so'm** |
| 10 | **Agent depoziti yetarli bo'lmaganda** | Tranzaksiya **rad etiladi** |
| 11 | **Termal chek kengligi** | **57mm va 80mm** — ikkalasi ham qo'llab-quvvatlanadi |
| 12 | **Dalolatnomani imzolash** | **Elektron imzo** |

### Komissiya hisoblash misoli:

| Tranzaksiya summasi | Komissiya (1%) | Kartadan jami yechiladi | Agent depozitiga kirim |
|---------------------|----------------|-------------------------|------------------------|
| 50 000 so'm | 500 so'm | 50 500 so'm | 50 000 so'm |
| 500 000 so'm | 5 000 so'm | 505 000 so'm | 500 000 so'm |
| 5 000 000 so'm | 50 000 so'm | 5 050 000 so'm | 5 000 000 so'm |
| 10 000 000 so'm | 100 000 so'm | 10 100 000 so'm | 10 000 000 so'm |

> **Eslatma:** Komissiya **mijoz** tomonidan to'lanadi (kartadan yechiladi). Agent depozitiga faqat asosiy summa kirim bo'ladi. Komissiya ELPAY MCHJ daromadi hisoblanadi.

---

*Hujjat tayyorlagan: ELPAY loyiha jamoasi*  
*Versiya: 1.0 | Sana: 2026-yil iyun*  
*Keyingi yangilanish: Bosqich 1 xulosasidan keyin*
