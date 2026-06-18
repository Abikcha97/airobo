#!/usr/bin/env python3
"""
Build 3 separate SVG sequence diagrams (Russian):
  1. SEQUENCE_HAPPY_PATH_RU.svg       — Основной поток
  2. SEQUENCE_ERROR_FLOWS_RU.svg      — Обработка ошибок
  3. SEQUENCE_MONTH_END_FLOW_RU.svg   — Ежемесячный поток вознаграждений
Pure Python stdlib only.
"""

import os

FONT = 'Arial, Helvetica, sans-serif'

C = {
    'hdr_bg':   '#1F3864', 'hdr_fg': '#FFFFFF',
    'lifeline': '#A0B4D0', 'arrow':  '#1F3864', 'ret':  '#2E5797',
    'g1': '#EBF5FF', 'g2': '#FFF8E6', 'g3': '#EBFFED',
    'g4': '#FFF0F0', 'g5': '#F5EBFF', 'ge': '#FFE8E8',
    'gm': '#E8F5E9', 'note_bg': '#FFFDE7', 'note_bd': '#F9A825',
    'err': '#C62828',
}

def esc(t):
    return str(t).replace('&','&amp;').replace('<','&lt;').replace('>','&gt;')

def tlines(txt, x, y, sz=11, fill='#000', anchor='middle', bold=False, gap=14):
    lines = txt.split('\n')
    total = (len(lines)-1)*gap
    sy = y - total/2
    w = 'bold' if bold else 'normal'
    out = []
    for i, ln in enumerate(lines):
        out.append(
            f'<text x="{x}" y="{sy+i*gap}" font-family="{FONT}" '
            f'font-size="{sz}" font-weight="{w}" fill="{fill}" '
            f'text-anchor="{anchor}" dominant-baseline="middle">{esc(ln)}</text>'
        )
    return '\n'.join(out)

def mh(label):
    return max(38, label.count('\n')*14 + 16)

DEFS = (
    '<defs>'
    '<marker id="a" markerWidth="8" markerHeight="8" refX="8" refY="3" orient="auto">'
    f'<path d="M0,0 L0,6 L8,3 z" fill="{C["arrow"]}"/></marker>'
    '<marker id="r" markerWidth="8" markerHeight="8" refX="8" refY="3" orient="auto">'
    f'<path d="M0,0 L0,6 L8,3 z" fill="{C["ret"]}"/></marker>'
    '<marker id="e" markerWidth="8" markerHeight="8" refX="8" refY="3" orient="auto">'
    f'<path d="M0,0 L0,6 L8,3 z" fill="{C["err"]}"/></marker>'
    '</defs>'
)

def render_svg(title, participants, messages,
               part_w=132, part_h=52, gap=36,
               margin_x=36, margin_y=20, title_h=38):
    N = len(participants)
    IDX = {p[0]: i for i, p in enumerate(participants)}
    def cx(i): return margin_x + i*(part_w+gap) + part_w//2
    total_w = margin_x*2 + N*(part_w+gap) - gap

    y = margin_y + title_h + part_h + 12
    rows = []
    grp_stack = []
    grp_ranges = []
    sep_rows = []

    for msg in messages:
        mt = msg[0]
        if mt == 'group':
            grp_stack.append((msg[1], msg[2], y)); continue
        if mt == 'group_end':
            if grp_stack:
                ck, lb, ys = grp_stack.pop()
                grp_ranges.append((ck, lb, ys, y))
            continue
        if mt == 'sep':
            sep_rows.append((msg[1], y)); y += 28; continue
        if mt == 'note':
            rows.append((msg, y+14)); y += 28; continue
        h = mh(msg[3])
        rows.append((msg, y + h/2))
        y += h

    total_h = y + part_h + 36
    els = []
    els.append(f'<svg xmlns="http://www.w3.org/2000/svg" width="{total_w}" height="{total_h}" style="background:#FAFCFF;">')
    els.append(DEFS)
    els.append(
        f'<rect x="0" y="0" width="{total_w}" height="{title_h}" fill="{C["hdr_bg"]}"/>'
        f'\n{tlines(title, total_w//2, title_h//2, sz=13, fill="#FFFFFF", bold=True)}'
    )
    for ck, lb, ys, ye in grp_ranges:
        x0 = margin_x - 8; w = total_w - margin_x*2 + 16; h = ye - ys
        els.append(
            f'<rect x="{x0}" y="{ys}" width="{w}" height="{h}" rx="5" '
            f'fill="{C[ck]}" stroke="{C["lifeline"]}" stroke-width="1" opacity="0.65"/>'
            f'\n{tlines(lb, x0+10, ys+14, sz=9.5, fill="#555", anchor="start", bold=True)}'
        )
    for lb, sy in sep_rows:
        x0 = margin_x - 8; w = total_w - margin_x*2 + 16
        els.append(
            f'<rect x="{x0}" y="{sy}" width="{w}" height="24" rx="3" '
            f'fill="{C["note_bg"]}" stroke="{C["note_bd"]}" stroke-width="1"/>'
            f'\n{tlines(lb, total_w//2, sy+12, sz=10, fill="#555", bold=True)}'
        )
    ll_top = margin_y + title_h + part_h
    ll_bot = total_h - part_h - 16
    for i in range(N):
        x = cx(i)
        els.append(f'<line x1="{x}" y1="{ll_top}" x2="{x}" y2="{ll_bot}" stroke="{C["lifeline"]}" stroke-width="1.5" stroke-dasharray="4,4"/>')
    for i, (_, lb) in enumerate(participants):
        bx = cx(i) - part_w//2; by = margin_y + title_h
        els.append(
            f'<rect x="{bx}" y="{by}" width="{part_w}" height="{part_h}" rx="6" fill="{C["hdr_bg"]}" stroke="#0D2040" stroke-width="1.5"/>'
            f'\n{tlines(lb, cx(i), by+part_h//2, sz=11, fill="#FFF", bold=True, gap=13)}'
        )
    seq = 1
    for msg, yc in rows:
        mt = msg[0]
        if mt == 'note':
            x0 = margin_x - 8; w = total_w - margin_x*2 + 16
            els.append(
                f'<rect x="{x0}" y="{yc-12}" width="{w}" height="24" rx="3" fill="{C["note_bg"]}" stroke="{C["note_bd"]}" stroke-width="1"/>'
                f'\n{tlines(msg[1], total_w//2, yc, sz=10, fill="#555")}'
            )
            continue
        frm, to, label = msg[1], msg[2], msg[3]
        fi = IDX[frm]; ti = IDX[to]
        x1 = cx(fi); x2 = cx(ti)
        is_ret = (mt == 'ret'); is_err = (mt == 'err')
        col = C['err'] if is_err else (C['ret'] if is_ret else C['arrow'])
        dash = '5,3' if is_ret else 'none'
        mk = 'e' if is_err else ('r' if is_ret else 'a')
        if mt == 'self':
            lx = x1 + 18
            els.append(
                f'<path d="M{x1},{yc-12} L{lx+20},{yc-12} L{lx+20},{yc+12} L{x1},{yc+12}" '
                f'fill="none" stroke="{col}" stroke-width="1.6" stroke-dasharray="{dash}" marker-end="url(#{mk})"/>'
                f'\n{tlines(f"[{seq}] {label}", lx+26, yc, sz=9.5, fill=col, anchor="start", gap=12)}'
            )
        else:
            ax = x2-6 if x2 > x1 else x2+6
            els.append(
                f'<line x1="{x1}" y1="{yc}" x2="{ax}" y2="{yc}" stroke="{col}" stroke-width="1.8" stroke-dasharray="{dash}" marker-end="url(#{mk})"/>'
                f'\n{tlines(f"[{seq}] {label}", (x1+x2)/2, yc-9, sz=9.5, fill=col, gap=12)}'
            )
        seq += 1
    bb = total_h - part_h - 16
    for i, (_, lb) in enumerate(participants):
        bx = cx(i) - part_w//2
        els.append(
            f'<rect x="{bx}" y="{bb}" width="{part_w}" height="{part_h}" rx="6" fill="{C["hdr_bg"]}" stroke="#0D2040" stroke-width="1.5"/>'
            f'\n{tlines(lb, cx(i), bb+part_h//2, sz=11, fill="#FFF", bold=True, gap=13)}'
        )
    els.append('</svg>')
    return '\n'.join(els)

def save(path, content):
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f'  ✓  {path}  ({os.path.getsize(path):,} bytes)')


# ═══════════════════════════════════════════════════════════════════════════
# DIAGRAM 1 — ОСНОВНОЙ ПОТОК (HAPPY PATH)
# ═══════════════════════════════════════════════════════════════════════════

HP_PARTS = [
    ('klient', '👤 Клиент'),
    ('kassir', '🧑‍💼 Кассир'),
    ('fe',     '🖥️ ELPAY\nFrontend\n(Web/POS)'),
    ('be',     '⚙️ ELPAY\nBackend'),
    ('oson',   '🏦 OSON\nProcessing'),
    ('sms',    '📱 SMS\nGateway'),
    ('db',     '🗄️ Депозит\nАгента DB'),
]

HP_MSGS = [
    ('group', 'g1', 'ЭТАП 1: Ввод данных карты'),
    ('msg',  'kassir','fe',     'Выбирает услугу\n"Снятие наличных с карты"'),
    ('msg',  'klient','kassir', 'Передаёт номер карты\nи срок действия'),
    ('msg',  'kassir','fe',     'Вводит card_number, expiry_date'),
    ('self', 'fe',   'fe',     'Проверка карты (алгоритм Луна)'),
    ('msg',  'fe',   'be',     'POST /cashout/initiate\n{card_number, expiry_date, agent_id}'),
    ('msg',  'be',   'oson',   'Запрос на проверку карты'),
    ('ret',  'oson', 'be',     '{card_holder, masked_phone} ✅'),
    ('group_end',),

    ('group', 'g2', 'ЭТАП 2: Отправка и проверка OTP'),
    ('msg',  'be',    'sms',    'Запрос на отправку OTP\n{phone: masked_phone}'),
    ('ret',  'sms',   'klient', 'SMS с OTP-кодом 📲'),
    ('ret',  'be',    'fe',     '{session_id, otp_sent: true}'),
    ('ret',  'fe',    'kassir', '"OTP отправлен. Попросите клиента назвать код"'),
    ('msg',  'kassir','klient', 'Запрашивает SMS-код'),
    ('ret',  'klient','kassir', 'Называет OTP-код'),
    ('msg',  'kassir','fe',     'Вводит OTP-код'),
    ('msg',  'fe',    'be',     'POST /cashout/verify-otp\n{session_id, otp_code}'),
    ('self', 'be',    'be',     'Проверка OTP (срок 60 сек)'),
    ('ret',  'be',    'fe',     '{verified: true, card_holder_name,\navailable_limit} ✅'),
    ('ret',  'fe',    'kassir', 'Имя владельца карты и лимит отображены'),
    ('group_end',),

    ('group', 'g3', 'ЭТАП 3: Ввод суммы'),
    ('msg',  'kassir','klient', '"Какую сумму снять?"'),
    ('ret',  'klient','kassir', 'Называет сумму'),
    ('msg',  'kassir','fe',     'Вводит сумму\n(50 000 – 10 000 000 сум)'),
    ('self', 'fe',    'fe',     'Рассчитывает комиссию (сумма × 1%)'),
    ('ret',  'fe',    'kassir', 'Комиссия и итог отображены\n500 000 + 5 000 = 505 000 сум'),
    ('group_end',),

    ('group', 'g4', 'ЭТАП 4: Выполнение транзакции'),
    ('msg',  'kassir','fe',    'Нажимает кнопку "Оплатить"'),
    ('msg',  'fe',    'be',    'POST /cashout/execute\n{session_id, amount, agent_id, operator_id}'),
    ('msg',  'be',    'db',    'Проверяет депозит агента'),
    ('ret',  'db',    'be',    'Депозит достаточен ✅'),
    ('self', 'be',    'be',    'Проверка дневного лимита (≤ 30 000 000 сум)'),
    ('msg',  'be',    'oson',  'Запрос на дебетование карты\n{сумма + 1% комиссии}'),
    ('self', 'oson',  'oson',  'Списывает с карты:\n• сумма → ELPAY\n• 1% комиссии удерживает OSON'),
    ('ret',  'oson',  'be',    '{status: success, txn_id} ✅'),
    ('msg',  'be',    'db',    'Зачисление на депозит агента (+основная сумма)'),
    ('ret',  'db',    'be',    'Депозит обновлён ✅'),
    ('self', 'be',    'be',    'Запись вознаграждения:\nагент 0.2% | ELPAY 0.2% | OSON 0.6%'),
    ('self', 'be',    'be',    'Запись в журнал аудита'),
    ('ret',  'be',    'fe',    '{transaction_id, status: success,\nreceipt_data, agent_balance_after}'),
    ('group_end',),

    ('group', 'g5', 'ЭТАП 5: Чек и выдача наличных'),
    ('ret',  'fe',    'kassir', 'Транзакция выполнена успешно ✅'),
    ('self', 'fe',    'fe',     'Генерация чека (PDF / термочек)'),
    ('ret',  'fe',    'kassir', 'Чек отправлен на принтер 🖨️'),
    ('msg',  'kassir','klient', 'Выдаёт наличные 💵'),
    ('msg',  'kassir','klient', 'Выдаёт чек 🧾'),
    ('group_end',),
]

svg1 = render_svg(
    'Основной поток (Happy Path) — Снятие наличных с карты | ELPAY',
    HP_PARTS, HP_MSGS,
    part_w=135, part_h=56, gap=34
)
save('/projects/sandbox/airobo/SEQUENCE_HAPPY_PATH_RU.svg', svg1)


# ═══════════════════════════════════════════════════════════════════════════
# DIAGRAM 2 — ОБРАБОТКА ОШИБОК (ERROR FLOWS)
# ═══════════════════════════════════════════════════════════════════════════

EF_PARTS = [
    ('kassir', '🧑‍💼 Кассир'),
    ('fe',     '🖥️ ELPAY\nFrontend'),
    ('be',     '⚙️ ELPAY\nBackend'),
    ('oson',   '🏦 OSON\nProcessing'),
]

EF_MSGS = [
    ('sep', '❌  ОШИБКА 1: Истёкший срок карты (Frontend-валидация)'),
    ('msg', 'kassir','fe',    'card_number, expiry_date\n(прошедшая дата)'),
    ('self','fe',   'fe',    'Проверка даты (frontend)'),
    ('err', 'fe',   'kassir','"Срок действия карты истёк" ❌'),

    ('sep', '❌  ОШИБКА 2: Недостаточно средств на карте'),
    ('msg', 'kassir','fe',   'Запрос на снятие (сумма, OTP подтверждён)'),
    ('msg', 'fe',   'be',    'POST /cashout/execute\n{session_id, amount, agent_id}'),
    ('msg', 'be',   'oson',  'Запрос на дебетование карты'),
    ('ret', 'oson', 'be',    '402 — Insufficient funds'),
    ('err', 'be',   'fe',    '"На карте недостаточно средств" ❌'),
    ('err', 'fe',   'kassir','"На карте недостаточно средств" ❌'),

    ('sep', '❌  ОШИБКА 3: OTP введён неверно 3 раза'),
    ('msg', 'kassir','fe',   'Неверный OTP-код (3-я попытка)'),
    ('msg', 'fe',   'be',    'POST /cashout/verify-otp\n{session_id, wrong_otp}'),
    ('self','be',   'be',    '3-я попытка — сессия блокируется'),
    ('err', 'be',   'fe',    '401 — "Сессия заблокирована. Начните заново" ❌'),
    ('err', 'fe',   'kassir','"Сессия отменена. Попробуйте снова" ❌'),

    ('sep', '❌  ОШИБКА 4: Депозит агента недостаточен'),
    ('msg', 'kassir','fe',   'Запрос на снятие (все проверки пройдены)'),
    ('msg', 'fe',   'be',    'POST /cashout/execute'),
    ('self','be',   'be',    'Проверка депозита агента'),
    ('err', 'be',   'fe',    '503 — "Услуга временно недоступна" ❌'),
    ('err', 'fe',   'kassir','"Обратитесь к администратору" ❌'),

    ('sep', '❌  ОШИБКА 5: Превышен дневной лимит'),
    ('msg', 'kassir','fe',   'Запрос на снятие\n(сумма > 30 000 000 сум)'),
    ('msg', 'fe',   'be',    'POST /cashout/execute'),
    ('self','be',   'be',    'Проверка лимита: сумма > 30 000 000'),
    ('err', 'be',   'fe',    '429 — "Дневной лимит исчерпан" ❌'),
    ('err', 'fe',   'kassir','"Дневной лимит на сегодня исчерпан" ❌'),
]

svg2 = render_svg(
    'Обработка ошибок (Error Flows) — Снятие наличных с карты | ELPAY',
    EF_PARTS, EF_MSGS,
    part_w=140, part_h=52, gap=40
)
save('/projects/sandbox/airobo/SEQUENCE_ERROR_FLOWS_RU.svg', svg2)


# ═══════════════════════════════════════════════════════════════════════════
# DIAGRAM 3 — ЕЖЕМЕСЯЧНЫЙ ПОТОК ВОЗНАГРАЖДЕНИЙ (MONTH-END FLOW)
# ═══════════════════════════════════════════════════════════════════════════

ME_PARTS = [
    ('cron', '⏰ Ежемесячный\nCron Job'),
    ('be',   '⚙️ ELPAY\nBackend'),
    ('oson', '🏦 OSON'),
    ('db',   '🗄️ Депозит\nАгента DB'),
    ('pdf',  '📄 PDF\nGenerator'),
]

ME_MSGS = [
    ('note', 'Запускается автоматически в конце каждого месяца'),

    ('group', 'g3', 'Шаг 1: Сбор данных и расчёт с OSON'),
    ('msg',  'cron','be',   'Инициирует процесс ежемесячного закрытия'),
    ('self', 'be',  'be',   'Собирает все cashout-транзакции\nза прошедший месяц'),
    ('msg',  'be',  'oson', 'Запрос на подтверждение счёта-фактуры'),
    ('ret',  'oson','be',   'Вознаграждение ELPAY (0.4%) перечислено ✅'),
    ('group_end',),

    ('group', 'gm', 'Шаг 2: Расчёт и выплата вознаграждения каждому агенту'),
    ('self', 'be',  'be',   'Для каждого агента:\nagent_reward = Σ(сумма_транзакции × 0.2%)'),
    ('msg',  'be',  'db',   'Зачисление вознаграждения агенту 1 (+0.2%)'),
    ('ret',  'db',  'be',   'Зачисление подтверждено ✅'),
    ('msg',  'be',  'pdf',  'Сгенерировать счёт-фактуру агента 1'),
    ('ret',  'pdf', 'be',   'PDF агента 1 готов 📄'),
    ('msg',  'be',  'db',   'Зачисление вознаграждения агенту 2 (+0.2%)'),
    ('ret',  'db',  'be',   'Зачисление подтверждено ✅'),
    ('msg',  'be',  'pdf',  'Сгенерировать счёт-фактуру агента 2'),
    ('ret',  'pdf', 'be',   'PDF агента 2 готов 📄'),
    ('note', '...  (повторяется для всех агентов)  ...'),
    ('group_end',),

    ('group', 'g5', 'Шаг 3: Сводный отчёт и завершение'),
    ('msg',  'be',  'pdf',  'Сгенерировать сводный отчёт\nOSON–ELPAY'),
    ('ret',  'pdf', 'be',   'Сводный PDF готов 📄'),
    ('self', 'be',  'be',   'Сохраняет транзакции вознаграждений\nв таблицу cashout_rewards'),
    ('ret',  'be',  'cron', 'Ежемесячное закрытие завершено ✅'),
    ('group_end',),

    ('note',
     'Распределение: Клиент 1% → OSON удерживает 0.6% | '
     'OSON → ELPAY 0.4% | ELPAY → Агент 0.2% | ELPAY чистый доход 0.2%'),
]

svg3 = render_svg(
    'Ежемесячный поток вознаграждений (Month-End Flow) — Снятие наличных с карты | ELPAY',
    ME_PARTS, ME_MSGS,
    part_w=148, part_h=54, gap=48
)
save('/projects/sandbox/airobo/SEQUENCE_MONTH_END_FLOW_RU.svg', svg3)

print('\nВсе файлы созданы!')
