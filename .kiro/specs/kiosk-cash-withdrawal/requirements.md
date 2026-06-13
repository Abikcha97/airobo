# Talablar Hujjati — Kiosk Cash Withdrawal Service

## Kirish

Ushbu hujjat kiosk terminallarida "Kartadan naqd pul yechish" xizmatini amalga oshirish uchun
texnik talablarni belgilaydi. Xizmat mavjud to'lov tizimiga (OSON yoki PAYNET provayderi orqali)
integratsiya qilinadi va PHP/Java backend, PostgreSQL/MySQL ma'lumotlar bazasida REST API sifatida
ishlab chiqiladi.

Tizim uchta tomonni qamrab oladi:
- **Mijoz** — kiosk terminali orqali o'z kartasidan naqd pul oluvchi shaxs
- **Agent** — kiosk terminallarini boshqaruvchi moliyaviy vositachi (terminal egasi)
- **Administrator** — tizimni boshqaruvchi va nazorat qiluvchi operator

---

## Glossariy

- **System** — Kiosk Cash Withdrawal Service (ushbu hujjatda ishlatiladigan asosiy tizim nomi)
- **Kiosk** — Naqd pul beruvchi dispenser qurilmasi o'rnatilgan o'z-o'ziga xizmat ko'rsatish terminali
- **Dispenser** — Kiosk tarkibidagi naqd banknotalarni beruvchi mexanik qurilma
- **Agent** — Kiosklarni operatsion boshqaruvchi va ular uchun moliyaviy javobgar bo'lgan yuridik yoki jismoniy shaxs
- **Agent_Deposit** — Agentning tizim ichidagi moliyaviy hisobi, kassa balansi
- **Card** — To'lov tizimi orqali ishlatiladigan bank plastik kartasi
- **OTP** — One-Time Password — bir martalik SMS parol
- **Check_Request** — Tranzaksiyadan oldin mijoz kartasi va kiosk dispenser balansini tekshiruvchi so'rov
- **Pay_Request** — OTP tasdiqlangandan so'ng tranzaksiyani yakunlovchi to'lov so'rovi
- **Payment_Provider** — Tashqi to'lov xizmat ko'rsatuvchisi (OSON yoki PAYNET)
- **Transaction** — Naqd pul yechish jarayonini ifodalovchi yozuv (holati, summasi, vaqti va boshqa metama'lumotlar)
- **Reconciliation** — Agent va tizim o'rtasidagi moliyaviy solishtirma dalolatnoma
- **Commission** — Tranzaksiyadan ushlab qolinadigan xizmat to'lovi (komissiya)
- **Agent_Reward** — Oylik naqd pul yechish hajmi asosida agentga hisoblangan mukofot pul
- **Admin_Panel** — Tizim administratori uchun boshqaruv interfeysi
- **Agent_Cabinet** — Agentning o'z faoliyatini kuzatishi uchun web-interfeysi
- **service_id** — Tizimda xizmatni identifikatsiya qiluvchi raqamli kod
- **agent_transaction_id** — Agent tomonidan generatsiya qilingan tranzaksiya identifikatori
- **transaction_id** — Payment Provider tomonidan berilgan global tranzaksiya identifikatori
- **checkTransactionId** — Check_Request natijasida olingan va Pay_Request-da qayta ishlatiladigan identifikator
- **Banknote** — Dispenser tomonidan saqlanadigan va beriladigan naqd pul birligi (bonton)
- **Refund** — Muvaffaqiyatsiz yoki bahsli tranzaksiya bo'yicha pulni qaytarish jarayoni

---

## Talablar

---

### Talab 1: Karta Ma'lumotlarini Qabul Qilish va Tasdiqlash

**User Story:** Mijoz sifatida, karta raqamim va amal qilish muddatimni kiritsam, tizim ularning
to'g'riligini darhol tekshirsin, shunda noto'g'ri ma'lumot kiritganimda vaqtimni behuda sarflamayman.

#### Acceptance Criteria

1. THE System SHALL karta raqami uchun 16 ta raqamdan iborat formatni qabul qilishi va Luhn algoritmi
   orqali tekshirishi KERAK
2. THE System SHALL karta amal qilish muddatini `MM/YY` formatida qabul qilishi KERAK
3. WHEN mijoz karta raqami kiritish maydoniga 16 ta raqamdan kam yoki ko'p raqam kiritsa,
   THE System SHALL "Karta raqami noto'g'ri formatda" xato xabarini ko'rsatishi KERAK
4. WHEN mijoz amal qilish muddatini o'tib ketgan sana sifatida kiritsa,
   THE System SHALL "Kartaning amal qilish muddati tugagan" xato xabarini ko'rsatishi KERAK
5. IF karta ma'lumotlari Payment Provider tomonidan topilmasa,
   THEN THE System SHALL "Karta topilmadi yoki faol emas" xato xabarini ko'rsatishi va tranzaksiyani
   to'xtatishi KERAK
6. THE System SHALL karta raqamini ekranda va logda `860014******5346` shaklida maskelash (qisman
   ko'rsatish) orqali saqlashi KERAK

---

### Talab 2: SMS OTP Yuborish va Tasdiqlash

**User Story:** Mijoz sifatida, kartam bilan bog'liq telefon raqamimga OTP kelsin va uni kiritganda
tizim tranzaksiyamni davom etirsin, shunda mening kartamni faqat men boshqarayotganimga ishonch hosil
bo'lsin.

#### Acceptance Criteria

1. WHEN karta ma'lumotlari muvaffaqiyatli tasdiqlansa, THE System SHALL kartaga ulangan telefon
   raqamiga 6 ta raqamdan iborat OTP yuborishi KERAK
2. THE System SHALL OTP kodni yuborilganidan boshlab 180 soniya (3 daqiqa) mobaynida amal qiladigan
   qilishi KERAK
3. WHEN mijoz to'g'ri OTP kodni kiritsa, THE System SHALL tranzaksiyani keyingi bosqichga o'tkazishi KERAK
4. WHEN mijoz noto'g'ri OTP kodni kiritsa, THE System SHALL "OTP kod noto'g'ri" xato xabarini
   ko'rsatishi KERAK
5. WHEN mijoz OTP kodni ketma-ket 3 marta noto'g'ri kiritsa, THE System SHALL tranzaksiyani bloklab
   "Ko'p urinish: tranzaksiya bekor qilindi" xabarini ko'rsatishi va 15 daqiqa mobaynida shu kartadan
   yangi urinishni rad etishi KERAK
6. WHEN OTP kodni amal qilish muddati tugasa va mijoz hali kirmagan bo'lsa, THE System SHALL
   "OTP kodning muddati tugadi, qayta urinib ko'ring" xabarini ko'rsatishi va yangi OTP yuborish
   imkonini berishi KERAK
7. THE System SHALL bir xil tranzaksiya uchun OTP qayta yuborish so'rovini 3 martadan oshirmaslik
   chegarasini qo'llashi KERAK
8. IF SMS xizmati javob qaytarmasa yoki xatolik yuz bersa, THEN THE System SHALL "SMS yuborishda
   xatolik yuz berdi, qayta urinib ko'ring" xabarini ko'rsatishi KERAK

---

### Talab 3: Yechish Summasini Tanlash va Tasdiqlash

**User Story:** Mijoz sifatida, yechmoqchi bo'lgan summani tanlash yoki qo'lda kiritish imkoniyatim
bo'lsin, shunda foydalanish jarayoni qulay va tezkor bo'lsin.

#### Acceptance Criteria

1. THE System SHALL tezkor tanlash uchun oldindan belgilangan summa variantlarini (masalan, 50 000,
   100 000, 200 000, 500 000 so'm) ekranda ko'rsatishi KERAK
2. THE System SHALL mijozga summa maydoni orqali ixtiyoriy summa kiritish imkonini berishi KERAK
3. THE System SHALL faqat 1 000 so'mning kattalashuvchi bo'luvchilariga mos summalarni qabul qilishi KERAK
4. WHEN mijoz 1 000 so'mning bo'luvchisiga mos kelmaydigan summa kiritsa, THE System SHALL
   "Summa 1 000 so'm karralilarida bo'lishi kerak" xato xabarini ko'rsatishi KERAK
5. THE System SHALL minimal yechish summasini 10 000 so'm qilib belgilashi KERAK
6. THE System SHALL maksimal yechish summasini bir tranzaksiya uchun 5 000 000 so'm bilan
   cheklashi KERAK
7. IF mijoz belgilangan minimal summadan kichik yoki maksimal summadan katta summa kiritsa,
   THEN THE System SHALL mos chegaraviy xato xabarini ko'rsatishi KERAK

---

### Talab 4: Check Request — Dispenser va Karta Balansini Tekshirish

**User Story:** Tizim operatori sifatida, naqd pul berishdan oldin kioskning kassasida yetarli
banknot va mijoz kartasida yetarli mablag' borligini tekshiraylik, shunda foydalanuvchi
pul kutib qolib muammoga duch kelmasin.

#### Acceptance Criteria

1. WHEN mijoz OTP kodni muvaffaqiyatli tasdiqlasa va summani kiritsa, THE System SHALL Payment
   Provider-ga Check_Request yuborishi KERAK
2. THE System SHALL Check_Request tarkibida quyidagi majburiy maydonlarni uzatishi KERAK:
   `amount`, `service_id`, `agent_transaction_id`, `params.card_expire`,
   `params.requestorPhone`, `account` (maskelangan karta raqami)
3. THE System SHALL Check_Request javobini 30 soniya ichida olishi KERAK; aks holda timeout
   xatoligi qaytarishi KERAK
4. WHEN Payment Provider Check_Request-ga muvaffaqiyatli javob qaytarsa, THE System SHALL
   tranzaksiyani Pay_Request bosqichiga o'tkazishi KERAK
5. WHEN Dispenser tarkibida mijoz so'ragan summani berish uchun yetarli banknotlar bo'lmasa,
   THE System SHALL "Kiosk kassasida yetarli naqd pul mavjud emas" xabarini ko'rsatishi va
   tranzaksiyani bekor qilishi KERAK
6. IF mijoz kartasida yetarli mablag' bo'lmasa, THEN THE System SHALL "Kartada yetarli mablag'
   mavjud emas" xabarini ko'rsatishi va tranzaksiyani bekor qilishi KERAK
7. THE System SHALL Check_Request va uning natijasini (muvaffaqiyatli yoki muvaffaqiyatsiz)
   tranzaksiya logiga yozishi KERAK
8. WHEN Check_Request muvaffaqiyatli bo'lsa, THE System SHALL Payment Provider-dan qaytgan
   `checkTransactionId` ni keyingi Pay_Request uchun saqlashi KERAK

---

### Talab 5: Pay Request — Tranzaksiyani Yakunlash va Naqd Pul Berish

**User Story:** Mijoz sifatida, barcha tekshiruvlardan o'tgandan so'ng kiosk dispenseridan
naqd pulimni olishni xohlayman, shunda xizmatdan foydalanish to'liq va samarali bo'lsin.

#### Acceptance Criteria

1. WHEN Check_Request muvaffaqiyatli bo'lsa, THE System SHALL Payment Provider-ga Pay_Request
   yuborishi KERAK
2. THE System SHALL Pay_Request tarkibida quyidagi majburiy maydonlarni uzatishi KERAK:
   `transaction_id`, `currencyId`, `checkTransactionId`, `params.sms_code`
3. WHEN Payment Provider Pay_Request-ga muvaffaqiyatli javob qaytarsa, THE System SHALL
   Dispenser-ga belgilangan summadagi banknotlarni berish buyrug'ini yuborishi KERAK
4. WHEN Dispenser banknotlarni muvaffaqiyatli bergandan so'ng, THE System SHALL tranzaksiyani
   "Muvaffaqiyatli" holati bilan yakunlashi va mijozga tasdiqlash xabari ko'rsatishi KERAK
5. WHEN Pay_Request javobi 30 soniya ichida kelmasa, THE System SHALL timeout xatoligini
   qayd qilishi va tranzaksiyani "Noaniq" holatiga o'tkazishi KERAK
6. IF Payment Provider Pay_Request-ni rad etsa, THEN THE System SHALL rad etish sababini
   logga yozishi va mijozga "To'lov amalga oshmadi, kassirga murojaat qiling" xabarini
   ko'rsatishi KERAK
7. WHEN Dispenser banknotlarni berish buyrug'iga javob qaytarmasa yoki xatolik yuz bersa,
   THE System SHALL bu holatni alohida "Dispenser xatoligi" kodi bilan qayd qilishi va
   administratorni ogohlantirishi KERAK
8. THE System SHALL Pay_Request, Dispenser javobi va yakuniy tranzaksiya holatini
   tranzaksiya logiga yozishi KERAK

---

### Talab 6: Tranzaksiya Holati Kuzatuvi

**User Story:** Administrator va agent sifatida, har bir tranzaksiyaning joriy holatini
real vaqtda kuzatishni xohlayman, shunda muammoli tranzaksiyalarni tezda aniqlash va
hal qilish imkoni bo'lsin.

#### Acceptance Criteria

1. THE System SHALL har bir tranzaksiyani quyidagi holatlardan birida saqlashi KERAK:
   `INITIATED` (boshlangan), `OTP_SENT` (OTP yuborilgan), `OTP_VERIFIED` (OTP tasdiqlangan),
   `CHECK_PENDING` (tekshiruv kutilmoqda), `CHECK_FAILED` (tekshiruv muvaffaqiyatsiz),
   `PAY_PENDING` (to'lov kutilmoqda), `DISPENSING` (banknotalar berilmoqda),
   `COMPLETED` (muvaffaqiyatli yakunlangan), `FAILED` (muvaffaqiyatsiz), `REFUNDED` (qaytarilgan)
2. THE System SHALL tranzaksiya holati o'zgargan har bir vaqt uchun `timestamp` yozishi KERAK
3. THE System SHALL tranzaksiya yozuvida quyidagi maydonlarni saqlashi KERAK:
   `transaction_id`, `agent_transaction_id`, `kiosk_id`, `agent_id`, `card_account` (maskelangan),
   `amount`, `commission`, `status`, `created_at`, `updated_at`, `payment_provider_response`
4. WHEN tranzaksiya "Noaniq" (`UNKNOWN`) holatida 5 daqiqadan ortiq qolsa,
   THE System SHALL administratorni bildirishnoma (notification) orqali xabardor qilishi KERAK
5. THE System SHALL tranzaksiya holatini so'rash uchun REST API endpointini taqdim etishi KERAK,
   bu endpoint `transaction_id` yoki `agent_transaction_id` bo'yicha qidiruvni qo'llab-quvvatlashi KERAK
6. THE System SHALL tranzaksiya ma'lumotlarini kamida 5 yil davomida saqlashi KERAK

---

### Talab 7: Mijoz Kartasidan Debit va Agent Depozitiga Kredit

**User Story:** Agent sifatida, mijoz muvaffaqiyatli naqd pul olganda kartadan yechildi
mablag' depozitimga tushishini xohlayman, shunda moliyaviy hisobim to'g'ri yuritilsin.

#### Acceptance Criteria

1. WHEN tranzaksiya `COMPLETED` holatiga o'tganda, THE System SHALL mijoz kartasidan
   naqd pul summasi va komissiyani yechishi KERAK
2. THE System SHALL yechilgan summaning komissiyasiz qismini Agent_Deposit hisobiga
   kirim qilishi KERAK
3. THE System SHALL komissiya summasini alohida hisobda to'planishi uchun qayd qilishi KERAK
4. THE System SHALL har bir debit/kredit operatsiyasini `double-entry` (qo'sh yozuv)
   moliyaviy hisob tamoyili asosida qayd qilishi KERAK
5. IF debit yoki kredit operatsiyasida xatolik yuz bersa, THEN THE System SHALL butun
   moliyaviy tranzaksiyani rollback qilishi va tranzaksiyani `FAILED` holatiga o'tkazishi KERAK
6. THE System SHALL Agent_Deposit joriy balansini real vaqtda ko'rsatadigan API endpointini
   taqdim etishi KERAK

---

### Talab 8: Agent Oborot va Solishtirma Dalolatnoma (Reconciliation)

**User Story:** Agent sifatida, kunlik va oylik oborotimni ko'rib, Payment Provider bilan
moliyaviy hisobimni solishtirishni xohlayman, shunda moliyaviy nazorat va hisobot to'g'ri
amalga oshirilsin.

#### Acceptance Criteria

1. THE System SHALL agent uchun kunlik solishtirma dalolatnomani avtomatik ravishda har
   kechaning 23:59 da shakllantirishi KERAK
2. THE System SHALL dalolatnomada quyidagi ma'lumotlarni o'z ichiga olishi KERAK:
   sana, agent_id, jami tranzaksiyalar soni, jami summa, jami komissiya, muvaffaqiyatli
   tranzaksiyalar, muvaffaqiyatsiz tranzaksiyalar va Agent_Deposit harakatlar ro'yxati
3. THE System SHALL dalolatnomani PDF va CSV formatlarida yuklab olish imkonini berishi KERAK
4. WHEN Payment Provider dalolatnomasi tizim ichki ma'lumotlari bilan mos kelmasa,
   THE System SHALL mos kelmaydigan tranzaksiyalarni alohida "Kelishmovchilik" bo'limida
   ko'rsatishi KERAK
5. THE System SHALL agent uchun ixtiyoriy sana oralig'i bo'yicha oborot hisobotini
   generatsiya qiladigan API endpointini taqdim etishi KERAK

---

### Talab 9: Oylik Agent Mukofot Puli (Agent Reward) Hisoblash

**User Story:** Agent sifatida, oylik tranzaksiya hajmim asosida mukofot pul olishni va
bu summaning avtomatik ravishda depozitimga tushishini xohlayman, shunda motivatsiyam
yuqori bo'lsin va hisob-kitob aniq amalga oshirilsin.

#### Acceptance Criteria

1. THE System SHALL har oy oxirida (oyning so'nggi kunida 23:59 da) agent mukofot pulini
   hisoblash jarayonini avtomatik ravishda boshlashi KERAK
2. THE System SHALL mukofot pulini to'liq oy davomidagi muvaffaqiyatli tranzaksiyalar
   jami summasiga asoslanib hisoblashi KERAK
3. THE System SHALL hisoblangan mukofot pulini Agent_Deposit hisobiga avtomatik kirim
   qilishi KERAK
4. THE System SHALL mukofot pul hisoblash natijalari haqida agentga bildirishnoma yuborishi KERAK
5. THE System SHALL mukofot pul hisoblash tarixi va tafsilotlarini Agent_Cabinet-da
   ko'rsatishi KERAK
6. WHERE mukofot pul stavkasi tizimdagi agent toifasiga qarab farqlanishi mumkin bo'lsa,
   THE System SHALL har bir agent uchun belgilangan stavkani qo'llashi KERAK

---

### Talab 10: Kiosk Dispenser Banknotalarini Boshqarish

**User Story:** Agent sifatida, kiosk terminalimga qanday denominatsiyadagi banknotlardan
qancha miqdorda joylashtirilganini kiritishni va shu ma'lumotlarni kuzatishni xohlayman,
shunda dispenser balansi nazorat ostida bo'lsin.

#### Acceptance Criteria

1. THE System SHALL agent yoki administrator tomonidan kiosk dispenser-ga banknotalar
   to'ldirilgan holat haqidagi ma'lumotni qabul qilishi KERAK
2. THE System SHALL banknotlar to'ldirish ma'lumotida quyidagi maydonlarni talab qilishi KERAK:
   `kiosk_id`, `denomination` (banknot nominal qiymati so'mda), `quantity` (miqdori),
   `filled_at` (to'ldirish vaqti), `filled_by` (kim tomonidan)
3. THE System SHALL har bir muvaffaqiyatli tranzaksiyadan so'ng dispenserdagi tegishli
   nominaldagi banknotlar miqdorini kamaytirishi KERAK
4. WHEN dispenser ichidagi biror nominaldagi banknotlar soni belgilangan minimal chegaradan
   (default: 20 ta) kamayib ketsa, THE System SHALL agentga va administratorga "Kiosk
   kassa miqdori kam" ogohlantirishini yuborishi KERAK
5. THE System SHALL dispenser joriy holati (har bir nominal bo'yicha qolgan banknotlar soni
   va jami summa) ни Admin_Panel va Agent_Cabinet-da real vaqtda ko'rsatishi KERAK
6. THE System SHALL dispenser to'ldirish va sarflash tarixini saqlab borishi va hisobot
   shaklida taqdim etishi KERAK

---

### Talab 11: To'lov Qaytarish (Refund)

**User Story:** Administrator sifatida, muvaffaqiyatsiz yoki bahsli tranzaksiya holatida
mijozga pulni qaytarish operatsiyasini boshlashni xohlayman, shunda mijoz moliyaviy zarar
ko'rmasin va tizimga ishonch saqlansa.

#### Acceptance Criteria

1. THE System SHALL `COMPLETED` holati bilan yakunlangan tranzaksiya uchun refund so'rovini
   qabul qilishi KERAK
2. THE System SHALL refund so'rovida `transaction_id`, `reason` (sabab) va `initiated_by`
   (kim tomonidan boshlangan) maydonlarini talab qilishi KERAK
3. WHEN refund muvaffaqiyatli amalga oshirilsa, THE System SHALL summani Agent_Deposit dan
   yechib, mijoz kartasiga qaytarishi KERAK
4. THE System SHALL refund operatsiyasini tranzaksiya logida alohida yozuv sifatida saqlashi KERAK
5. THE System SHALL bir tranzaksiya uchun faqat bir marta refund bajarilishiga ruxsat berishi KERAK
6. IF refund so'rovi allaqachon refund qilingan tranzaksiyaga nisbatan kelib tushsa,
   THEN THE System SHALL "Ushbu tranzaksiya allaqachon qaytarilgan" xato xabarini qaytarishi KERAK
7. THE System SHALL refund bajarish imkoniyatini faqat administrator roliga ega foydalanuvchilarga
   taqdim etishi KERAK
8. WHEN refund operatsiyasi boshlangandan so'ng, THE System SHALL tasdiqlashni Payment Provider-ga
   yuborishi va javobni tranzaksiya logiga yozishi KERAK

---

### Talab 12: Hisobot va Statistika

**User Story:** Administrator va agent sifatida, tranzaksiyalar bo'yicha hisobot va statistikani
ko'rishni xohlayman, shunda biznes qarorlarini ma'lumotlarga asoslanib qabul qilay.

#### Acceptance Criteria

1. THE System SHALL Admin_Panel-da quyidagi hisobotlarni ko'rsatishi KERAK:
   - Kunlik/haftalik/oylik tranzaksiyalar jami va tafsilotlari
   - Kiosk bo'yicha tranzaksiyalar statistikasi
   - Agent bo'yicha tranzaksiyalar statistikasi
   - Muvaffaqiyatsiz tranzaksiyalar nisbati va sabablari
2. THE System SHALL Agent_Cabinet-da agentga tegishli kiosk va tranzaksiyalar ma'lumotlarini
   ko'rsatishi KERAK (faqat o'z ma'lumotlari)
3. THE System SHALL hisobotlarni ixtiyoriy sana oralig'i, kiosk_id, agent_id va tranzaksiya
   holati bo'yicha filtrlash imkonini berishi KERAK
4. THE System SHALL hisobotlarni Excel (XLSX) va CSV formatlarida eksport qilish imkonini
   berishi KERAK
5. THE System SHALL har bir agent uchun joriy oy davomidagi jami oborot, tranzaksiyalar soni
   va taxminiy mukofot pul miqdorini real vaqtda ko'rsatadigan dashboard taqdim etishi KERAK
6. WHEN so'ralgan hisobot hajmi 10 000 ta yozuvdan oshsa, THE System SHALL hisobotni
   fon rejimida shakllantirish va tayyor bo'lganda yuklab olish havolasini taqdim etishi KERAK

---

### Talab 13: Autentifikatsiya va Avtorizatsiya

**User Story:** Tizim xavfsizligi uchun, faqat vakolatli foydalanuvchilar tegishli ma'lumotlarga
kirishi kerak, shunda moliyaviy ma'lumotlar va tranzaksiyalar himoyalangan bo'lsin.

#### Acceptance Criteria

1. THE System SHALL Admin_Panel va Agent_Cabinet-da foydalanuvchi autentifikatsiyasini
   JWT token asosida amalga oshirishi KERAK
2. THE System SHALL quyidagi rollarni qo'llab-quvvatlashi KERAK: `ADMIN`, `AGENT`, `KIOSK`
3. THE System SHALL har bir API endpoint uchun rol asosidagi kirish nazoratini (RBAC) qo'llashi KERAK
4. WHEN foydalanuvchi noto'g'ri parol bilan 5 marta kirmoqchi bo'lsa, THE System SHALL
   hisobni 30 daqiqa muddatga bloklashi KERAK
5. THE System SHALL JWT access token uchun 1 soatlik va refresh token uchun 30 kunlik
   amal qilish muddatini qo'llashi KERAK
6. THE System SHALL barcha API so'rovlarini HTTPS orqali qabul qilishi KERAK
7. THE System SHALL barcha foydalanuvchi harakatlari (login, refund, to'ldirish va boshqalar)
   uchun audit log yurgazishi KERAK

---

### Talab 14: Payment Provider Integratsiyasi

**User Story:** Backend dasturchi sifatida, tizim OSON yoki PAYNET provayderiga integratsiya
qilinishi kerak va almashtirish imkoni ham bo'lsin, shunda bitta provayderga bog'liqlik
kamaytirilib, xizmat uzluksizligi ta'minlansa.

#### Acceptance Criteria

1. THE System SHALL Payment Provider-ga ulanish uchun adapter pattern arxitekturasini
   qo'llashi va provayderlarni almashtirish konfiguratsiya orqali amalga oshiriladigan
   qilishi KERAK
2. THE System SHALL har bir Check_Request va Pay_Request uchun to'liq so'rov va javobni
   log sifatida saqlashi KERAK
3. WHEN Payment Provider-ga so'rov yuborish vaqtida tarmoq xatosi yuz bersa, THE System SHALL
   eksponensial kechikish (exponential backoff) bilan 3 martagacha qayta urinishi KERAK
4. THE System SHALL Payment Provider javobini standart ichki format (Internal Response DTO)
   ga o'giruvchi marshaling qatlamini taqdim etishi KERAK
5. WHERE Payment Provider test (sandbox) muhitida ishlash konfiguratsiyasi mavjud bo'lsa,
   THE System SHALL test muhitida haqiqiy tranzaksiyalarni amalga oshirmasdan integratsiyani
   tekshirish imkonini berishi KERAK

---

### Talab 15: Xatoliklarni Boshqarish va Tiklash (Error Handling & Recovery)

**User Story:** Tizim operatori sifatida, har qanday xatolik yuz berganda tizim pul
yo'qotmaslik holati keltirib chiqarmasdan o'z-o'zidan tiklana olishi kerak, shunda
moliyaviy xavf minimallashtirilsin.

#### Acceptance Criteria

1. WHEN Dispenser banknotalarni berish buyrug'ini oldi, lekin texnik muammo tufayli
   banknotlarni bermay qolsa va Pay_Request allaqachon muvaffaqiyatli bo'lgan bo'lsa,
   THE System SHALL bu holatni `DISPENSE_FAILED` kodi bilan qayd qilishi va
   avtomatik refund jarayonini boshlashi KERAK
2. THE System SHALL `DISPENSE_FAILED` holati uchun administrator va agentga SMS va
   tizim bildirishnomasi yuborishi KERAK
3. THE System SHALL tizim qayta ishga tushganda (restart) `PAY_PENDING` va `DISPENSING`
   holatidagi tranzaksiyalarni aniqlashi va ularning haqiqiy holatini Payment Provider
   bilan tekshirishi KERAK
4. THE System SHALL har bir critical operatsiya (Check_Request, Pay_Request, Dispenser
   buyrug'i) uchun aylanma kesish mexanizmi (circuit breaker) ni qo'llashi KERAK
5. IF Pay_Request muvaffaqiyatli bo'ldi, lekin Dispenser javobi 60 soniya ichida kelmasa,
   THEN THE System SHALL tranzaksiyani `DISPENSE_TIMEOUT` holati bilan qayd qilishi va
   administrator xabardor qilishi KERAK

---

## Texnik Cheklovlar

- Backend: PHP va Java (mavjud tizim arxitekturasiga mos ravishda)
- Ma'lumotlar bazasi: PostgreSQL (asosiy), MySQL (zarur hollarda)
- Arxitektura: REST API
- Barcha API endpointlar OpenAPI 3.0 (Swagger) formatida hujjatlashtirilishi KERAK
- Barcha moliyaviy hisob-kitoblar `BIGINT` yoki `DECIMAL(20,2)` tipida so'mda saqlanishi KERAK
- Tizim UTC+5 (O'zbekiston vaqti) da ishlashi KERAK
