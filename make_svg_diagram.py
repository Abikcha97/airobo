#!/usr/bin/env python3
"""
Generates a proper Sequence Diagram SVG for the ELPAY Cash Withdrawal flow.
Pure Python stdlib only.
"""

# ── SVG helpers ────────────────────────────────────────────────────────────

def e(t):
    return str(t).replace('&','&amp;').replace('<','&lt;').replace('>','&gt;')

# ── Layout constants ───────────────────────────────────────────────────────

COLORS = {
    'header_bg':   '#1F3864',
    'header_fg':   '#FFFFFF',
    'lifeline':    '#A0B4D0',
    'arrow':       '#1F3864',
    'note_bg':     '#FFF9E6',
    'note_border': '#E8C840',
    'rect1_bg':    '#EBF5FF',   # karta
    'rect2_bg':    '#FFF8E6',   # otp
    'rect3_bg':    '#EBFFED',   # summa
    'rect4_bg':    '#FFF0F0',   # tranzaksiya
    'rect5_bg':    '#F5EBFF',   # chek
    'msg_fg':      '#1A1A2E',
    'ret_fg':      '#2E5797',
    'alt_bg':      '#F8F8F8',
    'alt_border':  '#AAAAAA',
    'alt_label':   '#666666',
}

PART_W   = 130   # participant box width
PART_H   = 50    # participant box height
GAP      = 40    # gap between participants
MARGIN_X = 40    # left/right margin
MARGIN_Y = 20    # top margin
LINE_H   = 36    # vertical space per message
FONT     = 'Arial, Helvetica, sans-serif'
FONT_MONO = 'Courier New, monospace'

# ── Participants ───────────────────────────────────────────────────────────

participants = [
    ('mijoz',   '👤 Mijoz'),
    ('kassir',  '🧑‍💼 Kassir'),
    ('fe',      '🖥️ ELPAY\nFrontend'),
    ('be',      '⚙️ ELPAY\nBackend'),
    ('oson',    '🏦 OSON\nProcessing'),
    ('sms',     '📱 SMS\nGateway'),
    ('db',      '🗄️ Agent\nDepozit DB'),
]

N = len(participants)

def cx(idx):
    return MARGIN_X + idx * (PART_W + GAP) + PART_W // 2

# ── Messages ───────────────────────────────────────────────────────────────
# Each item: ('type', from_idx, to_idx, label, opt_note)
# type: 'msg' | 'ret' | 'self' | 'note' | 'group_start' | 'group_end' | 'alt'

IDX = {p[0]: i for i, p in enumerate(participants)}

GROUPS = [
    # (color_key, label, slice of msgs below)
    ('rect1_bg', '1-BOSQICH: Karta ma\'lumotlarini kiritish'),
    ('rect2_bg', '2-BOSQICH: OTP yuborish va tekshirish'),
    ('rect3_bg', '3-BOSQICH: Summa kiritish va komissiya'),
    ('rect4_bg', '4-BOSQICH: Tranzaksiyani bajarish'),
    ('rect5_bg', '5-BOSQICH: Chek va naqd pul berish'),
]

MSGS = [
    # ── Group 1 ──
    ('group', 'rect1_bg', '1-BOSQICH: Karta ma\'lumotlarini kiritish'),
    ('msg',  'kassir', 'fe',    '"Kartadan naqd yechish" xizmatini tanlaydi'),
    ('msg',  'mijoz',  'kassir','Karta raqami va muddatini beradi'),
    ('msg',  'kassir', 'fe',    'card_number, expiry_date kiritadi'),
    ('self', 'fe',     'fe',    'Luhn tekshiruvi (frontend)'),
    ('msg',  'fe',     'be',    'POST /cashout/initiate\n{card_number, expiry_date, agent_id}'),
    ('msg',  'be',     'oson',  'Karta tekshiruvi so\'rovi'),
    ('ret',  'oson',   'be',    '{card_holder, masked_phone} ✅'),
    ('group_end', None, None, None),

    # ── Group 2 ──
    ('group', 'rect2_bg', '2-BOSQICH: OTP yuborish va tekshirish'),
    ('msg',  'be',    'sms',    'OTP yuborish\n{phone: masked_phone}'),
    ('ret',  'sms',   'mijoz',  'SMS OTP kodi 📲'),
    ('ret',  'be',    'fe',     '{session_id, masked_phone, otp_sent: true}'),
    ('ret',  'fe',    'kassir', '"OTP yuborildi. Kodni oling"'),
    ('msg',  'kassir','mijoz',  'SMS kodni so\'raydi'),
    ('ret',  'mijoz', 'kassir', 'OTP kodni beradi'),
    ('msg',  'kassir','fe',     'OTP kodni kiritadi'),
    ('msg',  'fe',    'be',     'POST /cashout/verify-otp\n{session_id, otp_code}'),
    ('self', 'be',    'be',     'OTP tekshiruvi (60s muddati)'),
    ('ret',  'be',    'fe',     '{verified: true, card_holder_name,\navailable_limit} ✅'),
    ('ret',  'fe',    'kassir', 'Karta egasi ismi va limit ko\'rsatiladi'),
    ('group_end', None, None, None),

    # ── Group 3 ──
    ('group', 'rect3_bg', '3-BOSQICH: Summa kiritish'),
    ('msg',  'kassir','mijoz',  '"Qancha summa kerak?"'),
    ('ret',  'mijoz', 'kassir', 'Summa aytadi'),
    ('msg',  'kassir','fe',     'Summani kiritadi\n(50 000 – 10 000 000 so\'m)'),
    ('self', 'fe',    'fe',     'Komissiya hisoblanadi\n(summa × 1%)'),
    ('ret',  'fe',    'kassir', 'Komissiya va jami ko\'rsatiladi\n(500 000 + 5 000 = 505 000 so\'m)'),
    ('group_end', None, None, None),

    # ── Group 4 ──
    ('group', 'rect4_bg', '4-BOSQICH: Tranzaksiyani bajarish'),
    ('msg',  'kassir','fe',     '"To\'lash" tugmasi bosiladi'),
    ('msg',  'fe',    'be',     'POST /cashout/execute\n{session_id, amount, agent_id, operator_id}'),
    ('msg',  'be',    'db',     'Agent depozitini tekshiradi'),
    ('ret',  'db',    'be',     'Depozit yetarli ✅'),
    ('self', 'be',    'be',     'Kunlik limit tekshiruvi\n(≤ 30 000 000 so\'m)'),
    ('msg',  'be',    'oson',   'Karta debit so\'rovi\n{amount + 1% komissiya}'),
    ('self', 'oson',  'oson',   'Kartadan yechadi:\n• summa ELPAY ga\n• 1% komissiya OSON ushlab qoladi'),
    ('ret',  'oson',  'be',     '{status: success, txn_id} ✅'),
    ('msg',  'be',    'db',     'Agent depozitiga kirim\n(+asosiy summa)'),
    ('ret',  'db',    'be',     'Depozit yangilandi ✅'),
    ('self', 'be',    'be',     'Mukofot yozuvi saqlanadi:\n• agent_reward: 0.2%\n• elpay_net: 0.2%\n• oson_share: 0.6%'),
    ('self', 'be',    'be',     'Audit log yoziladi'),
    ('ret',  'be',    'fe',     '{transaction_id, status: success,\nreceipt_data, agent_balance_after}'),
    ('group_end', None, None, None),

    # ── Group 5 ──
    ('group', 'rect5_bg', '5-BOSQICH: Chek va naqd pul berish'),
    ('ret',  'fe',    'kassir', 'Tranzaksiya muvaffaqiyatli ✅'),
    ('self', 'fe',    'fe',     'Chek generatsiya (PDF/termal)'),
    ('ret',  'fe',    'kassir', 'Chek printerga yuboriladi 🖨️'),
    ('msg',  'kassir','mijoz',  'Naqd pulni beradi 💵'),
    ('msg',  'kassir','mijoz',  'Chekni beradi 🧾'),
    ('group_end', None, None, None),
]

# ── Render ─────────────────────────────────────────────────────────────────

def text_lines(txt, x, y, font_size=11, fill='#000', anchor='middle',
               bold=False, font=FONT, line_gap=14):
    """Multi-line text renderer."""
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
    return max(LINE_H, lines * 14 + 12)

def render():
    elements = []

    # ── first pass: compute Y positions ──
    y = MARGIN_Y + PART_H + 20
    rows = []          # (msg, y_center)
    group_stack = []   # (color_key, label, y_start)
    group_ranges = []  # (color_key, label, y_start, y_end)

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

    total_h = y + PART_H + 40
    total_w = MARGIN_X * 2 + N * (PART_W + GAP) - GAP

    # ── SVG open ──
    elements.append(
        f'<svg xmlns="http://www.w3.org/2000/svg" '
        f'width="{total_w}" height="{total_h}" '
        f'style="background:#FAFCFF;font-family:{FONT};">'
    )
    elements.append('<defs><marker id="arr" markerWidth="8" markerHeight="8" '
                    'refX="8" refY="3" orient="auto">'
                    f'<path d="M0,0 L0,6 L8,3 z" fill="{COLORS["arrow"]}"/>'
                    '</marker>'
                    '<marker id="arr_ret" markerWidth="8" markerHeight="8" '
                    'refX="8" refY="3" orient="auto">'
                    f'<path d="M0,0 L0,6 L8,3 z" fill="{COLORS["ret_fg"]}"/>'
                    '</marker></defs>')

    # ── Group colored backgrounds ──
    for ck, lbl, ys, ye in group_ranges:
        x0 = MARGIN_X - 10
        w  = total_w - MARGIN_X * 2 + 20
        h  = ye - ys
        elements.append(
            f'<rect x="{x0}" y="{ys}" width="{w}" height="{h}" rx="6" '
            f'fill="{COLORS[ck]}" stroke="{COLORS["lifeline"]}" '
            f'stroke-width="1" opacity="0.6"/>'
        )
        elements.append(
            f'<text x="{x0+8}" y="{ys+14}" font-size="10" font-weight="bold" '
            f'fill="#555" font-family="{FONT}">{e(lbl)}</text>'
        )

    # ── Lifelines ──
    for i, (_, _) in enumerate(participants):
        x = cx(i)
        elements.append(
            f'<line x1="{x}" y1="{MARGIN_Y+PART_H}" x2="{x}" y2="{total_h-PART_H-20}" '
            f'stroke="{COLORS["lifeline"]}" stroke-width="1.5" stroke-dasharray="4,4"/>'
        )

    # ── Participant boxes (top) ──
    for i, (_, label) in enumerate(participants):
        x = cx(i) - PART_W // 2
        # box
        elements.append(
            f'<rect x="{x}" y="{MARGIN_Y}" width="{PART_W}" height="{PART_H}" '
            f'rx="6" fill="{COLORS["header_bg"]}" stroke="#0D2040" stroke-width="1.5"/>'
        )
        elements.append(text_lines(label, cx(i), MARGIN_Y + PART_H//2,
                                   font_size=11, fill=COLORS['header_fg'],
                                   bold=True, line_gap=13))

    # ── Messages ──
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
                # self-call loop
                lx = x1 + 20
                elements.append(
                    f'<path d="M{x1},{yc-10} L{lx+20},{yc-10} L{lx+20},{yc+10} L{x1},{yc+10}" '
                    f'fill="none" stroke="{col}" stroke-width="1.5" '
                    f'stroke-dasharray="{dash}" marker-end="url(#{marker})"/>'
                )
                elements.append(text_lines(
                    f'[{seq_n}] {label}',
                    lx + 26, yc, font_size=9.5, fill=col, anchor='start', line_gap=12
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
                # label
                mx = (x1 + x2) / 2
                elements.append(text_lines(
                    f'[{seq_n}] {label}',
                    mx, yc - 8, font_size=9.5, fill=col, line_gap=12
                ))
            seq_n += 1

    # ── Participant boxes (bottom) ──
    by = total_h - PART_H - 20
    for i, (_, label) in enumerate(participants):
        x = cx(i) - PART_W // 2
        elements.append(
            f'<rect x="{x}" y="{by}" width="{PART_W}" height="{PART_H}" '
            f'rx="6" fill="{COLORS["header_bg"]}" stroke="#0D2040" stroke-width="1.5"/>'
        )
        elements.append(text_lines(label, cx(i), by + PART_H//2,
                                   font_size=11, fill=COLORS['header_fg'],
                                   bold=True, line_gap=13))

    elements.append('</svg>')
    return '\n'.join(elements)


svg = render()
out = '/projects/sandbox/airobo/SEQUENCE_DIAGRAM.svg'
with open(out, 'w', encoding='utf-8') as f:
    f.write(svg)

import os
size = os.path.getsize(out)
print(f'SVG created: {out}  ({size:,} bytes)')
print(f'Canvas size embedded in SVG.')
