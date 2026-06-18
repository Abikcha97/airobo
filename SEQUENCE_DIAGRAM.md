# Sequence Diagram — Kartadan Naqd Pul Yechish

## Ishtirokchilar:
- **Mijoz** — karta egasi
- **Kassir** — ELPAY agentining operatori
- **ELPAY Frontend** — Web yoki POS terminal
- **ELPAY Backend** — asosiy server
- **OSON Processing** — processing markazi
- **SMS Gateway** — OTP yuborish xizmati
- **Agent Depozit DB** — agent depoziti ma'lumotlar bazasi

---

## Asosiy Oqim (Happy Path)

```mermaid
sequenceDiagram
    autonumber

    actor Mijoz as 👤 Mijoz
    actor Kassir as 🧑‍💼 Kassir
    participant FE as 🖥️ ELPAY Frontend<br/>(Web / POS)
    participant BE as ⚙️ ELPAY Backend
    participant OSON as 🏦 OSON Processing
    participant SMS as 📱 SMS Gateway
    participant DB as 🗄️ Agent Depozit DB

    rect rgb(235, 245, 255)
        Note over Kassir,FE: 1-BOSQICH: Karta ma'lumotlarini kiritish
        Kassir->>FE: "Kartadan naqd yechish" xizmatini tanlaydi
        Mijoz->>Kassir: Karta raqami va muddatini beradi
        Kassir->>FE: card_number, expiry_date kiritadi
        FE->>FE: Luhn algoritmi bilan karta raqamini tekshiradi
        FE->>BE: POST /cashout/initiate<br/>{card_number, expiry_date, agent_id}
        BE->>OSON: Karta tekshiruvi so'rovi
        OSON-->>BE: Karta ma'lumotlari tasdiqlandi<br/>{card_holder, masked_phone}
    end

    rect rgb(255, 248, 230)
        Note over BE,SMS: 2-BOSQICH: OTP yuborish va tekshirish
        BE->>SMS: OTP yuborish so'rovi<br/>{phone: masked_phone}
        SMS-->>Mijoz: SMS OTP kodi yuboriladi 📲
        BE-->>FE: {session_id, masked_phone, otp_sent: true}
        FE-->>Kassir: "OTP yuborildi. Mijozdan kodni oling"
        Kassir->>Mijoz: SMS kodni so'raydi
        Mijoz->>Kassir: OTP kodni beradi
        Kassir->>FE: OTP kodni kiritadi
        FE->>BE: POST /cashout/verify-otp<br/>{session_id, otp_code}
        BE->>BE: OTP muddati (60s) va to'g'riligini tekshiradi

        alt OTP noto'g'ri
            BE-->>FE: 401 — "Noto'g'ri kod. X urinish qoldi"
            FE-->>Kassir: Xato xabari ko'rsatiladi
        else OTP to'g'ri
            BE-->>FE: {verified: true, card_holder_name, available_limit}
            FE-->>Kassir: Karta egasi ismi va limit ko'rsatiladi ✅
        end
    end

    rect rgb(235, 255, 240)
        Note over Kassir,FE: 3-BOSQICH: Summa kiritish
        Kassir->>Mijoz: "Qancha summa kerak?" deb so'raydi
        Mijoz->>Kassir: Summa aytadi
        Kassir->>FE: Summani kiritadi (50 000 – 10 000 000 so'm)
        FE->>FE: Komissiya hisoblaydi (summa × 1%)
        FE-->>Kassir: Komissiya va jami ko'rsatiladi<br/>(masalan: 500 000 + 5 000 = 505 000 so'm)
    end

    rect rgb(255, 240, 240)
        Note over Kassir,DB: 4-BOSQICH: Tranzaksiyani bajarish
        Kassir->>FE: "To'lash" tugmasini bosadi
        FE->>BE: POST /cashout/execute<br/>{session_id, amount, agent_id, operator_id}

        BE->>DB: Agent depozitini tekshiradi
        DB-->>BE: Depozit holati

        alt Agent depozitida yetarli mablag' yo'q
            BE-->>FE: 503 — "Xizmat vaqtincha mavjud emas"
            FE-->>Kassir: Xato xabari
        else Depozit yetarli
            BE->>BE: Kunlik limitni tekshiradi (≤ 30 000 000 so'm)

            alt Limit oshib ketgan
                BE-->>FE: 429 — "Kunlik limit oshib ketdi"
                FE-->>Kassir: Xato xabari
            else Limit ichida
                BE->>OSON: Karta debit so'rovi<br/>{amount + komissiya}
                OSON->>OSON: Kartadan pul yechadi<br/>(summa + 1% komissiya)
                OSON-->>BE: {status: success, txn_id, deducted: amount+commission}

                BE->>DB: Agent depozitiga kirim<br/>(+asosiy summa)
                DB-->>BE: Depozit yangilandi ✅

                BE->>BE: Mukofot yozuvini saqlaydi<br/>(agent: 0.2%, ELPAY: 0.2%, OSON: 0.6%)
                BE->>BE: Audit log yozadi

                BE-->>FE: {transaction_id, status: success,<br/>receipt_data, agent_balance_after}
            end
        end
    end

    rect rgb(245, 235, 255)
        Note over Kassir,Mijoz: 5-BOSQICH: Chek va naqd pul berish
        FE-->>Kassir: Tranzaksiya muvaffaqiyatli ✅<br/>Chek ma'lumotlari ko'rsatiladi
        FE->>FE: Chek generatsiya qilinadi (PDF / termal)
        Kassir->>FE: "Chek chiqarish" tugmasini bosadi
        FE-->>Kassir: Chek printerga yuboriladi 🖨️
        Kassir->>Mijoz: Naqd pulni beradi 💵
        Kassir->>Mijoz: Chekni beradi 🧾
    end
```

---

## Xato Holatlari (Error Flows)

```mermaid
sequenceDiagram
    autonumber
    actor Kassir as 🧑‍💼 Kassir
    participant FE as 🖥️ ELPAY Frontend
    participant BE as ⚙️ ELPAY Backend
    participant OSON as 🏦 OSON Processing

    Note over Kassir,OSON: XATO 1: Karta muddati o'tgan
    Kassir->>FE: card_number, expiry_date (o'tgan sana)
    FE->>FE: Sana tekshiruvi (frontend validatsiya)
    FE-->>Kassir: ❌ "Kartaning amal qilish muddati tugagan"

    Note over Kassir,OSON: XATO 2: Kartada mablag' yetarli emas
    Kassir->>FE: execute so'rovi
    FE->>BE: POST /cashout/execute
    BE->>OSON: Debit so'rovi
    OSON-->>BE: 402 — Insufficient funds
    BE-->>FE: "Kartada yetarli mablag' mavjud emas"
    FE-->>Kassir: ❌ Xato xabari

    Note over Kassir,OSON: XATO 3: OTP 3 marta noto'g'ri kiritildi
    Kassir->>FE: Noto'g'ri OTP (3-urinish)
    FE->>BE: POST /cashout/verify-otp
    BE->>BE: 3-urinish — sessiya bloklanadi
    BE-->>FE: 401 — "Sessiya bloklandi. Qaytadan boshlang"
    FE-->>Kassir: ❌ Sessiya bekor qilindi
```

---

## Oylik Mukofot Oqimi (Month-End Flow)

```mermaid
sequenceDiagram
    autonumber
    participant Cron as ⏰ Oylik Cron Job
    participant BE as ⚙️ ELPAY Backend
    participant OSON as 🏦 OSON
    participant DB as 🗄️ Agent Depozit DB
    participant PDF as 📄 PDF Generator

    Note over Cron,PDF: Har oy oxirida avtomatik ishga tushadi

    Cron->>BE: Oylik yopilish jarayonini boshlaydi
    BE->>BE: O'tgan oy barcha cashout tranzaksiyalarini yig'adi

    BE->>OSON: Hisob-faktura tasdiqlash so'rovi
    OSON-->>BE: ELPAY mukofoti (0.4%) o'tkaziladi

    BE->>BE: Har bir agent uchun mukofot hisoblanadi<br/>(txn_summa × 0.2%)

    loop Har bir agent uchun
        BE->>DB: Agent depozitiga mukofot kirim<br/>(+0.2%)
        DB-->>BE: Kirim tasdiqlandi ✅
        BE->>PDF: Agent hisob-fakturasini generatsiya qil
        PDF-->>BE: PDF tayyor
    end

    BE->>PDF: OSON–ELPAY umumiy hisobotni generatsiya qil
    PDF-->>BE: PDF tayyor

    BE->>BE: Barcha mukofot tranzaksiyalarini<br/>cashout_rewards jadvaliga yozadi
    BE-->>Cron: Oylik yopilish yakunlandi ✅
```

---

## Ishtirokchilar va Ma'suliyat

| Ishtirokchi | Rol | Javobgar tomon |
|-------------|-----|----------------|
| 👤 Mijoz | Karta egasi, naqd oluvchi | — |
| 🧑‍💼 Kassir | ELPAY agenti operatori | Agent MCHJ/YaTT |
| 🖥️ ELPAY Frontend | Web / Android POS UI | Frontend dasturchi |
| ⚙️ ELPAY Backend | Biznes logika, API | Backend dasturchi |
| 🏦 OSON Processing | Karta processing, komissiya | OSON (3-tomon) |
| 📱 SMS Gateway | OTP yuborish | OSON integrator |
| 🗄️ Agent Depozit DB | Depozit holati | Backend dasturchi |
| ⏰ Cron Job | Oylik mukofot taqsimoti | Backend dasturchi |
