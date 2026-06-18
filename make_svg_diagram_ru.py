#!/usr/bin/env python3
"""
Generates a Sequence Diagram SVG (Russian) for ELPAY Cash Withdrawal flow.
Pure Python stdlib only.
"""

def e(t):
    return str(t).replace('&','&amp;').replace('<','&lt;').replace('>','&gt;')

COLORS = {
    'header_bg':   '#1F3864',
    'header_fg':   '#FFFFFF',
    'lifeline':    '#A0B4D0',
    'arrow':       '#1F3864',
    'ret_fg':      '#2E5797',
    'rect1_bg':    '#EBF5FF',
    'rect2_bg':    '#FFF8E6',
    'rect3_bg':    '#EBFFED',
    'rect4_bg':    '#FFF0F0',
    'rect5_bg':    '#F5EBFF',
}

PART_W   = 135
PART_H   = 54
GAP      = 38
MARGIN_X = 40
MARGIN_Y = 20
LINE_H   = 38
FONT     = 'Arial, Helvetica, sans-serif'

participants = [
    ('klient',  '👤 Клиент'),
    ('kassir',  '🧑‍💼 Кассир'),
    ('fe',      '🖥️ ELPAY\nFrontend'),
    ('be',      '⚙️ ELPAY\nBackend'),
    ('oson',    '🏦 OSON\nProcessing'),
    ('sms',     '📱 SMS\nGateway'),
    ('db',      '🗄️ Депозит\nАгента DB'),
]

N = len(participants)

def cx(idx):
    return MARGIN_X + idx * (PART_W + GAP) + PART_W // 2

def text_lines(txt, x, y, font_size=11, fill='#000', anchor='middle',
               bold=False, font=FONT, line_gap=14):
    lines = txt.split('\n')
    out = []
    total_h = (len(lines) - 1) * line_gap
    start_y = y - total_h / 2
    weight = 'bold' if bold else 'normal'
    for i, line in enumerate(lines):
        ly = start_y + i * line_gap
        out.append(
            f'<text x="{x}" y="{ly}" font-family="{font}" font-size="{font_size}" '
            f'font-weight="{weight}" fill="{fill}" text-anchor="{anchor}" '
            f'dominant-baseline="middle">{e(line)}</text>'
        )
    return '\n'.join(out)

def measure_msg_height(label):
    lines = label.count('\n') + 1
    return max(LINE_H, lines * 14 + 14)

IDX = {p[0]: i for i, p in enumerate(participants)}

MSGS = [
    # ── Этап 1 ──
    ('group', 'rect1_bg', 'ЭТАП 1: Ввод данных карты'),
    ('msg',  'kassir', 'fe',     'Выбирает услугу\n"Снятие наличных с карты"'),
    ('msg',  'klient', 'kassir', 'Передаёт номер карты\nи срок действия'),
    ('msg',  'kassir', 'fe',     'Вводит card_number, expiry_date'),
    ('self', 'fe',     'fe',     'Проверка карты (алгоритм Луна)'),
    ('msg',  'fe',     'be',     'POST /cashout/initiate\n{card_number, expiry_date, agent_id}'),
    ('msg',  'be',     'oson',   'Запрос на проверку карты'),
    ('ret',  'oson',   'be',     '{card_holder, masked_phone} ✅'),
    ('group_end', None, None, None),

    # ── Этап 2 ──
    ('group', 'rect2_bg', 'ЭТАП 2: Отправка и проверка OTP'),
    ('msg',  'be',     'sms',    'Запрос на отправку OTP\n{phone: masked_phone}'),
    ('ret',  'sms',    'klient', 'SMS с OTP-кодом 📲'),
    ('ret',  'be',     'fe',     '{session_id, masked_phone, otp_sent: true}'),
    ('ret',  'fe',     'kassir', '"OTP отправлен. Попросите клиента\nназвать код"'),
    ('msg',  'kassir', 'klient', 'Запрашивает SMS-код'),
    ('ret',  'klient', 'kassir', 'Называет OTP-код'),
    ('msg',  'kassir', 'fe',     'Вводит OTP-код'),
    ('msg',  'fe',     'be',     'POST /cashout/verify-otp\n{session_id, otp_code}'),
    ('self', 'be',     'be',     'Проверка OTP\n(срок 60 сек, корректность)'),
    ('ret',  'be',     'fe',     '{verified: true, card_holder_name,\navailable_limit} ✅'),
    ('ret',  'fe',     'kassir', 'Имя владельца и лимит отображены'),
    ('group_end', None, None, None),

    # ── Этап 3 ──
    ('group', 'rect3_bg', 'ЭТАП 3: Ввод суммы'),
    ('msg',  'kassir', 'klient', '"Какую сумму снять?"'),
    ('ret',  'klient', 'kassir', 'Называет сумму'),
    ('msg',  'kassir', 'fe',     'Вводит сумму\n(50 000 – 10 000 000 сум)'),
    ('self', 'fe',     'fe',     'Рассчитывает комиссию\n(сумма × 1%)'),
    ('ret',  'fe',     'kassir', 'Комиссия и итог отображены\n(500 000 + 5 000 = 505 000 сум)'),
    ('group_end', None, None, None),

    # ── Этап 4 ──
    ('group', 'rect4_bg', 'ЭТАП 4: Выполнение транзакции'),
    ('msg',  'kassir', 'fe',     'Нажимает кнопку "Оплатить"'),
    ('msg',  'fe',     'be',     'POST /cashout/execute\n{session_id, amount, agent_id, operator_id}'),
    ('msg',  'be',     'db',     'Проверяет депозит агента'),
    ('ret',  'db',     'be',     'Депозит достаточен ✅'),
    ('self', 'be',     'be',     'Проверка дневного лимита\n(≤ 30 000 000 сум)'),
    ('msg',  'be',     'oson',   'Запрос на дебетование карты\n{сумма + 1% комиссии}'),
    ('self', 'oson',   'oson',   'Списывает с карты:\n• сумма → ELPAY\n• 1% комиссии удерживает OSON'),
    ('ret',  'oson',   'be',     '{status: success, txn_id} ✅'),
    ('msg',  'be',     'db',     'Зачисление на депозит агента\n(+основная сумма)'),
    ('ret',  'db',     'be',     'Депозит обновлён ✅'),
    ('self', 'be',     'be',     'Сохраняется запись о вознаграждении:\n• agent_reward: 0.2%\n• elpay_net: 0.2%\n• oson_share: 0.6%'),
    ('self', 'be',     'be',     'Запись в журнал аудита'),
    ('ret',  'be',     'fe',     '{transaction_id, status: success,\nreceipt_data, agent_balance_after}'),
    ('group_end', None, None, None),

    # ── Этап 5 ──
    ('group', 'rect5_bg', 'ЭТАП 5: Чек и выдача наличных'),
    ('ret',  'fe',     'kassir', 'Транзакция выполнена успешно ✅'),
    ('self', 'fe',     'fe',     'Генерация чека (PDF/термочек)'),
    ('ret',  'fe',     'kassir', 'Чек отправлен на принтер 🖨️'),
    ('msg',  'kassir', 'klient', 'Выдаёт наличные 💵'),
    ('msg',  'kassir', 'klient', 'Выдаёт чек 🧾'),
    ('group_end', None, None, None),
]

def render():
    elements = []
    y = MARGIN_Y + PART_H + 20
    rows = []
    group_stack = []
    group_ranges = []

    for msg in MSGS:
        mtype = msg[0]
        if mtype == 'group':
            group_stack.append((msg[1], msg[2], y))
            continue
        if mtype == 'group_end':
            if group_stack:
                ck, lbl, ys = group_stack.pop()
                group_ranges.append((ck, lbl, ys, y))
            continue
        h = measure_msg_height(msg[3]) if msg[3] else LINE_H
        rows.append((msg, y + h / 2))
        y += h

    total_h = y + PART_H + 50
    total_w = MARGIN_X * 2 + N * (PART_W + GAP) - GAP

    elements.append(
        f'<svg xmlns="http://www.w3.org/2000/svg" '
        f'width="{total_w}" height="{total_h}" '
        f'style="background:#FAFCFF;font-family:{FONT};">'
    )
    elements.append(
        '<defs>'
        '<marker id="arr" markerWidth="8" markerHeight="8" refX="8" refY="3" orient="auto">'
        f'<path d="M0,0 L0,6 L8,3 z" fill="{COLORS["arrow"]}"/></marker>'
        '<marker id="arr_ret" markerWidth="8" markerHeight="8" refX="8" refY="3" orient="auto">'
        f'<path d="M0,0 L0,6 L8,3 z" fill="{COLORS["ret_fg"]}"/></marker>'
        '</defs>'
    )

    # Title
    elements.append(
        f'<rect x="0" y="0" width="{total_w}" height="36" fill="{COLORS["header_bg"]}"/>'
        f'<text x="{total_w//2}" y="22" font-family="{FONT}" font-size="14" font-weight="bold" '
        f'fill="#FFFFFF" text-anchor="middle" dominant-baseline="middle">'
        f'Диаграмма последовательности — Снятие наличных с карты (ELPAY)</text>'
    )

    # Group backgrounds
    for ck, lbl, ys, ye in group_ranges:
        x0 = MARGIN_X - 10
        w  = total_w - MARGIN_X * 2 + 20
        h  = ye - ys
        elements.append(
            f'<rect x="{x0}" y="{ys}" width="{w}" height="{h}" rx="6" '
            f'fill="{COLORS[ck]}" stroke="{COLORS["lifeline"]}" stroke-width="1" opacity="0.65"/>'
        )
        elements.append(
            f'<text x="{x0+10}" y="{ys+16}" font-size="10" font-weight="bold" '
            f'fill="#444" font-family="{FONT}">{e(lbl)}</text>'
        )

    # Lifelines
    for i in range(N):
        x = cx(i)
        elements.append(
            f'<line x1="{x}" y1="{MARGIN_Y+PART_H+36}" x2="{x}" y2="{total_h-PART_H-20}" '
            f'stroke="{COLORS["lifeline"]}" stroke-width="1.5" stroke-dasharray="4,4"/>'
        )

    # Participant boxes (top)
    for i, (_, label) in enumerate(participants):
        bx = cx(i) - PART_W // 2
        by = MARGIN_Y + 36
        elements.append(
            f'<rect x="{bx}" y="{by}" width="{PART_W}" height="{PART_H}" '
            f'rx="6" fill="{COLORS["header_bg"]}" stroke="#0D2040" stroke-width="1.5"/>'
        )
        elements.append(text_lines(label, cx(i), by + PART_H//2,
                                   font_size=11, fill=COLORS['header_fg'],
                                   bold=True, line_gap=13))

    # Messages
    seq_n = 1
    for msg, yc in rows:
        mtype, frm, to, label = msg[0], msg[1], msg[2], msg[3]
        if mtype in ('msg', 'ret', 'self'):
            fi = IDX[frm]
            ti = IDX[to]
            x1 = cx(fi)
            x2 = cx(ti)
            is_ret = (mtype == 'ret')
            col = COLORS['ret_fg'] if is_ret else COLORS['arrow']
            dash = '5,3' if is_ret else 'none'
            marker = 'arr_ret' if is_ret else 'arr'

            if mtype == 'self':
                lx = x1 + 20
                elements.append(
                    f'<path d="M{x1},{yc-12} L{lx+22},{yc-12} L{lx+22},{yc+12} L{x1},{yc+12}" '
                    f'fill="none" stroke="{col}" stroke-width="1.5" '
                    f'stroke-dasharray="{dash}" marker-end="url(#{marker})"/>'
                )
                elements.append(text_lines(
                    f'[{seq_n}] {label}',
                    lx + 28, yc, font_size=9.5, fill=col, anchor='start', line_gap=12
                ))
            else:
                if x2 > x1:
                    ax, ay = x2 - 6, yc
                else:
                    ax, ay = x2 + 6, yc
                elements.append(
                    f'<line x1="{x1}" y1="{yc}" x2="{ax}" y2="{ay}" '
                    f'stroke="{col}" stroke-width="1.8" stroke-dasharray="{dash}" '
                    f'marker-end="url(#{marker})"/>'
                )
                mx = (x1 + x2) / 2
                elements.append(text_lines(
                    f'[{seq_n}] {label}',
                    mx, yc - 9, font_size=9.5, fill=col, line_gap=12
                ))
            seq_n += 1

    # Participant boxes (bottom)
    bb = total_h - PART_H - 20
    for i, (_, label) in enumerate(participants):
        bx = cx(i) - PART_W // 2
        elements.append(
            f'<rect x="{bx}" y="{bb}" width="{PART_W}" height="{PART_H}" '
            f'rx="6" fill="{COLORS["header_bg"]}" stroke="#0D2040" stroke-width="1.5"/>'
        )
        elements.append(text_lines(label, cx(i), bb + PART_H//2,
                                   font_size=11, fill=COLORS['header_fg'],
                                   bold=True, line_gap=13))

    elements.append('</svg>')
    return '\n'.join(elements)


svg = render()
out = '/projects/sandbox/airobo/SEQUENCE_DIAGRAM_RU.svg'
with open(out, 'w', encoding='utf-8') as f:
    f.write(svg)

import os
print(f'SVG (RU) created: {out}  ({os.path.getsize(out):,} bytes)')
