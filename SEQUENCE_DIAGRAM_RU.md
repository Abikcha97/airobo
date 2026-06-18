# Диаграмма последовательности — Снятие наличных с карты

## Участники:
- **Клиент** — владелец карты
- **Кассир** — оператор агента ELPAY
- **ELPAY Frontend** — Web или POS-терминал
- **ELPAY Backend** — основной сервер
- **OSON Processing** — процессинговый центр
- **SMS Gateway** — сервис отправки OTP
- **Agent Deposit DB** — база данных депозита агента

---

## Основной поток (Happy Path)

```mermaid
sequenceDiagram
    autonumber

    actor Klient as 👤 Клиент
    actor Kassir as 🧑‍💼 Кассир
    participant FE as 🖥️ ELPAY Frontend<br/>(Web / POS)
    participant BE as ⚙️ ELPAY Backend
    participant OSON as 🏦 OSON Processing
    participant SMS as 📱 SMS Gateway
    participant DB as 🗄️ Депозит Агента DB

    rect rgb(235, 245, 255)
        Note over Kassir,FE: ЭТАП 1: Ввод данных карты
        Kassir->>FE: Выбирает услугу "Снятие наличных с карты"
        Klient->>Kassir: Передаёт номер карты и срок действия
        Kassir->>FE: Вводит card_number, expiry_date
        FE->>FE: Проверка номера карты (алгоритм Луна)
        FE->>BE: POST /cashout/initiate<br/>{card_number, expiry_date, agent_id}
        BE->>OSON: Запрос на проверку карты
        OSON-->>BE: Данные карты подтверждены<br/>{card_holder, masked_phone}
    end

    rect rgb(255, 248, 230)
        Note over BE,SMS: ЭТАП 2: Отправка и проверка OTP
        BE->>SMS: Запрос на отправку OTP<br/>{phone: masked_phone}
        SMS-->>Klient: SMS с OTP-кодом 📲
        BE-->>FE: {session_id, masked_phone, otp_sent: true}
        FE-->>Kassir: "OTP отправлен. Попросите клиента назвать код"
        Kassir->>Klient: Запрашивает SMS-код
        Klient->>Kassir: Называет OTP-код
        Kassir->>FE: Вводит OTP-код
        FE->>BE: POST /cashout/verify-otp<br/>{session_id, otp_code}
        BE->>BE: Проверка OTP (срок 60 сек, корректность)

        alt Неверный OTP
            BE-->>FE: 401 — "Неверный код. Осталось X попыток"
            FE-->>Kassir: Отображается сообщение об ошибке
        else OTP верный
            BE-->>FE: {verified: true, card_holder_name, available_limit}
            FE-->>Kassir: Отображается имя владельца карты и лимит ✅
        end
    end

    rect rgb(235, 255, 240)
        Note over Kassir,FE: ЭТАП 3: Ввод суммы
        Kassir->>Klient: Спрашивает: "Какую сумму снять?"
        Klient->>Kassir: Называет сумму
        Kassir->>FE: Вводит сумму (50 000 – 10 000 000 сум)
        FE->>FE: Рассчитывает комиссию (сумма × 1%)
        FE-->>Kassir: Отображает комиссию и итоговую сумму<br/>(500 000 + 5 000 = 505 000 сум)
    end

    rect rgb(255, 240, 240)
        Note over Kassir,DB: ЭТАП 4: Выполнение транзакции
        Kassir->>FE: Нажимает кнопку "Оплатить"
        FE->>BE: POST /cashout/execute<br/>{session_id, amount, agent_id, operator_id}

        BE->>DB: Проверяет депозит агента
        DB-->>BE: Состояние депозита

        alt Депозит агента недостаточен
            BE-->>FE: 503 — "Услуга временно недоступна"
            FE-->>Kassir: Сообщение об ошибке
        else Депозит достаточен
            BE->>BE: Проверка дневного лимита (≤ 30 000 000 сум)

            alt Лимит превышен
                BE-->>FE: 429 — "Дневной лимит исчерпан"
                FE-->>Kassir: Сообщение об ошибке
            else В пределах лимита
                BE->>OSON: Запрос на дебетование карты<br/>{сумма + комиссия}
                OSON->>OSON: Списывает с карты<br/>(сумма + 1% комиссии)
                OSON-->>BE: {status: success, txn_id}

                BE->>DB: Зачисление на депозит агента<br/>(+основная сумма)
                DB-->>BE: Депозит обновлён ✅

                BE->>BE: Сохраняет запись о вознаграждении<br/>(агент: 0.2%, ELPAY: 0.2%, OSON: 0.6%)
                BE->>BE: Запись в журнал аудита

                BE-->>FE: {transaction_id, status: success,<br/>receipt_data, agent_balance_after}
            end
        end
    end

    rect rgb(245, 235, 255)
        Note over Kassir,Klient: ЭТАП 5: Чек и выдача наличных
        FE-->>Kassir: Транзакция выполнена успешно ✅
        FE->>FE: Генерация чека (PDF / термочек)
        Kassir->>FE: Нажимает "Распечатать чек"
        FE-->>Kassir: Отправляет чек на принтер 🖨️
        Kassir->>Klient: Выдаёт наличные 💵
        Kassir->>Klient: Выдаёт чек 🧾
    end
```

---

## Обработка ошибок (Error Flows)

```mermaid
sequenceDiagram
    autonumber
    actor Kassir as 🧑‍💼 Кассир
    participant FE as 🖥️ ELPAY Frontend
    participant BE as ⚙️ ELPAY Backend
    participant OSON as 🏦 OSON Processing

    Note over Kassir,OSON: ОШИБКА 1: Истёкший срок карты
    Kassir->>FE: card_number, expiry_date (прошедшая дата)
    FE->>FE: Проверка даты (frontend-валидация)
    FE-->>Kassir: ❌ "Срок действия карты истёк"

    Note over Kassir,OSON: ОШИБКА 2: Недостаточно средств на карте
    Kassir->>FE: Запрос на выполнение транзакции
    FE->>BE: POST /cashout/execute
    BE->>OSON: Запрос на дебетование
    OSON-->>BE: 402 — Insufficient funds
    BE-->>FE: "На карте недостаточно средств"
    FE-->>Kassir: ❌ Сообщение об ошибке

    Note over Kassir,OSON: ОШИБКА 3: OTP введён неверно 3 раза
    Kassir->>FE: Неверный OTP (3-я попытка)
    FE->>BE: POST /cashout/verify-otp
    BE->>BE: 3-я попытка — сессия блокируется
    BE-->>FE: 401 — "Сессия заблокирована. Начните заново"
    FE-->>Kassir: ❌ Сессия отменена
```

---

## Ежемесячный поток вознаграждений (Month-End Flow)

```mermaid
sequenceDiagram
    autonumber
    participant Cron as ⏰ Ежемесячный<br/>Cron Job
    participant BE as ⚙️ ELPAY Backend
    participant OSON as 🏦 OSON
    participant DB as 🗄️ Депозит Агента DB
    participant PDF as 📄 PDF Generator

    Note over Cron,PDF: Запускается автоматически в конце каждого месяца

    Cron->>BE: Инициирует процесс ежемесячного закрытия
    BE->>BE: Собирает все cashout-транзакции за прошедший месяц

    BE->>OSON: Запрос на подтверждение счёта-фактуры
    OSON-->>BE: Перечисление вознаграждения ELPAY (0.4%)

    BE->>BE: Рассчитывает вознаграждение для каждого агента<br/>(сумма_транзакций × 0.2%)

    loop Для каждого агента
        BE->>DB: Зачисление вознаграждения на депозит агента<br/>(+0.2%)
        DB-->>BE: Зачисление подтверждено ✅
        BE->>PDF: Генерация счёта-фактуры агента
        PDF-->>BE: PDF готов
    end

    BE->>PDF: Генерация сводного отчёта OSON–ELPAY
    PDF-->>BE: PDF готов

    BE->>BE: Сохраняет все транзакции вознаграждений<br/>в таблице cashout_rewards
    BE-->>Cron: Ежемесячное закрытие завершено ✅
```

---

## Участники и ответственность

| Участник | Роль | Ответственная сторона |
|----------|------|-----------------------|
| 👤 Клиент | Владелец карты, получатель наличных | — |
| 🧑‍💼 Кассир | Оператор агента ELPAY | Агент ООО/ИП |
| 🖥️ ELPAY Frontend | Web / Android POS UI | Frontend-разработчик |
| ⚙️ ELPAY Backend | Бизнес-логика, API | Backend-разработчик |
| 🏦 OSON Processing | Процессинг карт, комиссия | OSON (3-я сторона) |
| 📱 SMS Gateway | Отправка OTP | Интегратор OSON |
| 🗄️ Депозит Агента DB | Состояние депозита | Backend-разработчик |
| ⏰ Cron Job | Ежемесячное распределение вознаграждений | Backend-разработчик |
