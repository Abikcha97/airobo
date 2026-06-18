#!/usr/bin/env python3
"""
Build 3 separate SVG sequence diagrams (O'zbek tili):
  1. SEQUENCE_HAPPY_PATH.svg       — Asosiy oqim
  2. SEQUENCE_ERROR_FLOWS.svg      — Xato holatlari
  3. SEQUENCE_MONTH_END_FLOW.svg   — Oylik mukofot oqimi
Pure Python stdlib only.
"""

import os

# ═══════════════════════════════════════════════════════════════════════════
# SHARED HELPERS
# ═══════════════════════════════════════════════════════════════════════════

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

def tlines(txt, x, y, sz=11, fill='#000', anchor='middle',
           bold=False, gap=14):
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


# ═══════════════════════════════════════════════════════════════════════════
# CORE RENDERER
# ═══════════════════════════════════════════════════════════════════════════

def render_svg(title, participants, messages,
               part_w=132, part_h=52, gap=36,
               margin_x=36, margin_y=20, title_h=38):
    """
    participants: list of (key, label)   label may contain \n
    messages: list of tuples, types:
      ('group', color_key, label)
      ('group_end',)
      ('note', label)              — full-width note strip
      ('msg',  frm, to, label)     — solid arrow →
      ('ret',  frm, to, label)     — dashed arrow -->
      ('self', key, key, label)    — loop back
      ('err',  frm, to, label)     — red arrow
      ('sep', label)               — separator note across all
    """
    N = len(participants)
    IDX = {p[0]: i for i, p in enumerate(participants)}

    def cx(i): return margin_x + i*(part_w+gap) + part_w//2

    total_w = margin_x*2 + N*(part_w+gap) - gap

    # ── first pass: compute y positions ─────────────────────────────────
    y = margin_y + title_h + part_h + 12
    rows = []
    grp_stack = []
    grp_ranges = []
    sep_rows = []

    for msg in messages:
        mt = msg[0]
        if mt == 'group':
            grp_stack.append((msg[1], msg[2], y))
            continue
        if mt == 'group_end':
            if grp_stack:
                ck, lb, ys = grp_stack.pop()
                grp_ranges.append((ck, lb, ys, y))
            continue
        if mt == 'sep':
            sep_rows.append((msg[1], y))
            y += 28
            continue
        if mt == 'note':
            rows.append((msg, y+14))
            y += 28
            continue
        h = mh(msg[3])
        rows.append((msg, y + h/2))
        y += h

    total_h = y + part_h + 36

    els = []
    els.append(
        f'<svg xmlns="http://www.w3.org/2000/svg" '
        f'width="{total_w}" height="{total_h}" '
        f'style="background:#FAFCFF;">'
    )
    els.append(DEFS)

    # title bar
    els.append(
        f'<rect x="0" y="0" width="{total_w}" height="{title_h}" fill="{C["hdr_bg"]}"/>'
        f'\n{tlines(title, total_w//2, title_h//2, sz=13, fill="#FFFFFF", bold=True)}'
    )

    # group backgrounds
    for ck, lb, ys, ye in grp_ranges:
        x0 = margin_x - 8
        w  = total_w - margin_x*2 + 16
        h  = ye - ys
        els.append(
            f'<rect x="{x0}" y="{ys}" width="{w}" height="{h}" rx="5" '
            f'fill="{C[ck]}" stroke="{C["lifeline"]}" stroke-width="1" opacity="0.65"/>'
            f'\n{tlines(lb, x0+10, ys+14, sz=9.5, fill="#555", anchor="start", bold=True)}'
        )

    # separator notes
    for lb, sy in sep_rows:
        x0 = margin_x - 8
        w  = total_w - margin_x*2 + 16
        els.append(
            f'<rect x="{x0}" y="{sy}" width="{w}" height="24" rx="3" '
            f'fill="{C["note_bg"]}" stroke="{C["note_bd"]}" stroke-width="1"/>'
            f'\n{tlines(lb, total_w//2, sy+12, sz=10, fill="#555", bold=True)}'
        )

    # lifelines
    ll_top = margin_y + title_h + part_h
    ll_bot = total_h - part_h - 16
    for i in range(N):
        x = cx(i)
        els.append(
            f'<line x1="{x}" y1="{ll_top}" x2="{x}" y2="{ll_bot}" '
            f'stroke="{C["lifeline"]}" stroke-width="1.5" stroke-dasharray="4,4"/>'
        )

    # participant boxes top
    for i, (_, lb) in enumerate(participants):
        bx = cx(i) - part_w//2
        by = margin_y + title_h
        els.append(
            f'<rect x="{bx}" y="{by}" width="{part_w}" height="{part_h}" '
            f'rx="6" fill="{C["hdr_bg"]}" stroke="#0D2040" stroke-width="1.5"/>'
            f'\n{tlines(lb, cx(i), by+part_h//2, sz=11, fill="#FFF", bold=True, gap=13)}'
        )

    # messages
    seq = 1
    for msg, yc in rows:
        mt = msg[0]
        if mt == 'note':
            x0 = margin_x - 8
            w  = total_w - margin_x*2 + 16
            els.append(
                f'<rect x="{x0}" y="{yc-12}" width="{w}" height="24" rx="3" '
                f'fill="{C["note_bg"]}" stroke="{C["note_bd"]}" stroke-width="1"/>'
                f'\n{tlines(msg[1], total_w//2, yc, sz=10, fill="#555")}'
            )
            continue

        frm, to, label = msg[1], msg[2], msg[3]
        fi = IDX[frm]; ti = IDX[to]
        x1 = cx(fi);  x2 = cx(ti)

        is_ret = (mt == 'ret')
        is_err = (mt == 'err')
        col    = C['err'] if is_err else (C['ret'] if is_ret else C['arrow'])
        dash   = '5,3'    if is_ret else 'none'
        mk     = 'e'      if is_err else ('r' if is_ret else 'a')

        if mt == 'self':
            lx = x1 + 18
            els.append(
                f'<path d="M{x1},{yc-12} L{lx+20},{yc-12} '
                f'L{lx+20},{yc+12} L{x1},{yc+12}" '
                f'fill="none" stroke="{col}" stroke-width="1.6" '
                f'stroke-dasharray="{dash}" marker-end="url(#{mk})"/>'
                f'\n{tlines(f"[{seq}] {label}", lx+26, yc, sz=9.5, fill=col, anchor="start", gap=12)}'
            )
        else:
            ax = x2-6 if x2 > x1 else x2+6
            els.append(
                f'<line x1="{x1}" y1="{yc}" x2="{ax}" y2="{yc}" '
                f'stroke="{col}" stroke-width="1.8" stroke-dasharray="{dash}" '
                f'marker-end="url(#{mk})"/>'
                f'\n{tlines(f"[{seq}] {label}", (x1+x2)/2, yc-9, sz=9.5, fill=col, gap=12)}'
            )
        seq += 1

    # participant boxes bottom
    bb = total_h - part_h - 16
    for i, (_, lb) in enumerate(participants):
        bx = cx(i) - part_w//2
        els.append(
            f'<rect x="{bx}" y="{bb}" width="{part_w}" height="{part_h}" '
            f'rx="6" fill="{C["hdr_bg"]}" stroke="#0D2040" stroke-width="1.5"/>'
            f'\n{tlines(lb, cx(i), bb+part_h//2, sz=11, fill="#FFF", bold=True, gap=13)}'
        )

    els.append('</svg>')
    return '\n'.join(els)


def save(path, content):
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f'  ✓  {path}  ({os.path.getsize(path):,} bytes)')


# ═══════════════════════════════════════════════════════════════════════════
# DIAGRAM 1 — ASOSIY OQIM (HAPPY PATH)
# ═══════════════════════════════════════════════════════════════════════════

HP_PARTS = [
    ('mijoz',  '👤 Mijoz'),
    ('kassir', '🧑‍💼 Kassir'),
    ('fe',     '🖥️ ELPAY\nFrontend\n(Web/POS)'),
    ('be',     '⚙️ ELPAY\nBackend'),
    ('oson',   '🏦 OSON\nProcessing'),
    ('sms',    '📱 SMS\nGateway'),
    ('db',     '🗄️ Agent\nDepozit DB'),
]

HP_MSGS = [
    ('group', 'g1', '1-BOSQICH: Karta ma\'lumotlarini kiritish'),
    ('msg',  'kassir','fe',    '"Kartadan naqd yechish"\nxizmatini tanlaydi'),
    ('msg',  'mijoz', 'kassir','Karta raqami va muddatini beradi'),
    ('msg',  'kassir','fe',    'card_number, expiry_date kiritadi'),
    ('self', 'fe',   'fe',    'Luhn tekshiruvi (frontend)'),
    ('msg',  'fe',   'be',    'POST /cashout/initiate\n{card_number, expiry_date, agent_id}'),
    ('msg',  'be',   'oson',  'Karta tekshiruvi so\'rovi'),
    ('ret',  'oson', 'be',    '{card_holder, masked_phone} ✅'),
    ('group_end',),

    ('group', 'g2', '2-BOSQICH: OTP yuborish va tekshirish'),
    ('msg',  'be',    'sms',   'OTP yuborish so\'rovi\n{phone: masked_phone}'),
    ('ret',  'sms',   'mijoz', 'SMS OTP kodi 📲'),
    ('ret',  'be',    'fe',    '{session_id, otp_sent: true}'),
    ('ret',  'fe',    'kassir','"OTP yuborildi. Kodni oling"'),
    ('msg',  'kassir','mijoz', 'SMS kodni so\'raydi'),
    ('ret',  'mijoz', 'kassir','OTP kodni beradi'),
    ('msg',  'kassir','fe',    'OTP kodni kiritadi'),
    ('msg',  'fe',    'be',    'POST /cashout/verify-otp\n{session_id, otp_code}'),
    ('self', 'be',    'be',    'OTP tekshiruvi (muddati 60s)'),
    ('ret',  'be',    'fe',    '{verified: true, card_holder_name,\navailable_limit} ✅'),
    ('ret',  'fe',    'kassir','Karta egasi ismi va limit ko\'rsatiladi'),
    ('group_end',),

    ('group', 'g3', '3-BOSQICH: Summa kiritish'),
    ('msg',  'kassir','mijoz', '"Qancha summa kerak?"'),
    ('ret',  'mijoz', 'kassir','Summa aytadi'),
    ('msg',  'kassir','fe',    'Summani kiritadi\n(50 000 – 10 000 000 so\'m)'),
    ('self', 'fe',    'fe',    'Komissiya hisoblanadi (summa × 1%)'),
    ('ret',  'fe',    'kassir','Komissiya va jami ko\'rsatiladi\n500 000 + 5 000 = 505 000 so\'m'),
    ('group_end',),

    ('group', 'g4', '4-BOSQICH: Tranzaksiyani bajarish'),
    ('msg',  'kassir','fe',   '"To\'lash" tugmasi bosiladi'),
    ('msg',  'fe',    'be',   'POST /cashout/execute\n{session_id, amount, agent_id, operator_id}'),
    ('msg',  'be',    'db',   'Agent depozitini tekshiradi'),
    ('ret',  'db',    'be',   'Depozit yetarli ✅'),
    ('self', 'be',    'be',   'Kunlik limit tekshiruvi (≤ 30 000 000 so\'m)'),
    ('msg',  'be',    'oson', 'Karta debit so\'rovi\n{amount + 1% komissiya}'),
    ('self', 'oson',  'oson', 'Kartadan yechadi:\n• summa → ELPAY\n• 1% komissiya → OSON ushlab qoladi'),
    ('ret',  'oson',  'be',   '{status: success, txn_id} ✅'),
    ('msg',  'be',    'db',   'Agent depozitiga kirim (+asosiy summa)'),
    ('ret',  'db',    'be',   'Depozit yangilandi ✅'),
    ('self', 'be',    'be',   'Mukofot yozuvi:\nagent 0.2% | ELPAY 0.2% | OSON 0.6%'),
    ('self', 'be',    'be',   'Audit log yoziladi'),
    ('ret',  'be',    'fe',   '{transaction_id, status: success,\nreceipt_data, agent_balance_after}'),
    ('group_end',),

    ('group', 'g5', '5-BOSQICH: Chek va naqd pul berish'),
    ('ret',  'fe',    'kassir','Tranzaksiya muvaffaqiyatli ✅'),
    ('self', 'fe',    'fe',    'Chek generatsiya (PDF / termal)'),
    ('ret',  'fe',    'kassir','Chek printerga yuboriladi 🖨️'),
    ('msg',  'kassir','mijoz', 'Naqd pulni beradi 💵'),
    ('msg',  'kassir','mijoz', 'Chekni beradi 🧾'),
    ('group_end',),
]

svg1 = render_svg(
    'Asosiy Oqim (Happy Path) — Kartadan Naqd Pul Yechish | ELPAY',
    HP_PARTS, HP_MSGS,
    part_w=132, part_h=56, gap=34
)
save('/projects/sandbox/airobo/SEQUENCE_HAPPY_PATH.svg', svg1)


# ═══════════════════════════════════════════════════════════════════════════
# DIAGRAM 2 — XATO HOLATLARI (ERROR FLOWS)
# ═══════════════════════════════════════════════════════════════════════════

EF_PARTS = [
    ('kassir', '🧑‍💼 Kassir'),
    ('fe',     '🖥️ ELPAY\nFrontend'),
    ('be',     '⚙️ ELPAY\nBackend'),
    ('oson',   '🏦 OSON\nProcessing'),
]

EF_MSGS = [
    # ── Xato 1 ──
    ('sep', '❌  XATO 1: Karta muddati o\'tgan (Frontend validatsiya)'),
    ('msg', 'kassir','fe',   'card_number, expiry_date\n(o\'tgan muddatli karta)'),
    ('self','fe',    'fe',   'Sana tekshiruvi (frontend)'),
    ('err', 'fe',    'kassir','"Kartaning amal qilish muddati tugagan" ❌'),

    # ── Xato 2 ──
    ('sep', '❌  XATO 2: Kartada mablag\' yetarli emas'),
    ('msg', 'kassir','fe',  'Yechish so\'rovi (summa, OTP tasdiqlangan)'),
    ('msg', 'fe',    'be',  'POST /cashout/execute\n{session_id, amount, agent_id}'),
    ('msg', 'be',    'oson','Karta debit so\'rovi'),
    ('ret', 'oson',  'be',  '402 — Insufficient funds'),
    ('err', 'be',    'fe',  '"Kartada yetarli mablag\' mavjud emas" ❌'),
    ('err', 'fe',    'kassir','"Kartada yetarli mablag\' mavjud emas" ❌'),

    # ── Xato 3 ──
    ('sep', '❌  XATO 3: OTP 3 marta noto\'g\'ri kiritildi'),
    ('msg', 'kassir','fe',  'Noto\'g\'ri OTP kodi (3-urinish)'),
    ('msg', 'fe',    'be',  'POST /cashout/verify-otp\n{session_id, wrong_otp}'),
    ('self','be',    'be',  '3-urinish — sessiya bloklanadi'),
    ('err', 'be',    'fe',  '401 — "Sessiya bloklandi. Qaytadan boshlang" ❌'),
    ('err', 'fe',    'kassir','"Sessiya bekor qilindi. Qayta urinib ko\'ring" ❌'),

    # ── Xato 4 ──
    ('sep', '❌  XATO 4: Agent depoziti yetarli emas'),
    ('msg', 'kassir','fe',  'Yechish so\'rovi (barcha tekshiruvlar o\'tgan)'),
    ('msg', 'fe',    'be',  'POST /cashout/execute'),
    ('msg', 'be',    'be',  'Agent depozitini tekshiradi'),
    ('err', 'be',    'fe',  '503 — "Xizmat vaqtincha mavjud emas" ❌'),
    ('err', 'fe',    'kassir','"Administratorga murojaat qiling" ❌'),

    # ── Xato 5 ──
    ('sep', '❌  XATO 5: Kunlik limit oshib ketdi'),
    ('msg', 'kassir','fe',  'Yechish so\'rovi\n(30 000 000 so\'m dan ortiq summa)'),
    ('msg', 'fe',    'be',  'POST /cashout/execute'),
    ('self','be',    'be',  'Limit tekshiruvi: summa > 30 000 000'),
    ('err', 'be',    'fe',  '429 — "Kunlik limit oshib ketdi" ❌'),
    ('err', 'fe',    'kassir','"Bugungi kunlik limit to\'ldi" ❌'),
]

svg2 = render_svg(
    'Xato Holatlari (Error Flows) — Kartadan Naqd Pul Yechish | ELPAY',
    EF_PARTS, EF_MSGS,
    part_w=140, part_h=52, gap=40
)
save('/projects/sandbox/airobo/SEQUENCE_ERROR_FLOWS.svg', svg2)


# ═══════════════════════════════════════════════════════════════════════════
# DIAGRAM 3 — OYLIK MUKOFOT OQIMI (MONTH-END FLOW)
# ═══════════════════════════════════════════════════════════════════════════

ME_PARTS = [
    ('cron', '⏰ Oylik\nCron Job'),
    ('be',   '⚙️ ELPAY\nBackend'),
    ('oson', '🏦 OSON'),
    ('db',   '🗄️ Agent\nDepozit DB'),
    ('pdf',  '📄 PDF\nGenerator'),
]

ME_MSGS = [
    ('note', 'Har oy oxirida avtomatik ishga tushadi'),

    ('group', 'g3', 'Bosqich 1: Ma\'lumot yig\'ish va OSON hisob-kitob'),
    ('msg',  'cron','be',  'Oylik yopilish jarayonini boshlaydi'),
    ('self', 'be',  'be',  'O\'tgan oy barcha cashout\ntranzaksiyalarini yig\'adi'),
    ('msg',  'be',  'oson','Hisob-faktura tasdiqlash so\'rovi'),
    ('ret',  'oson','be',  'ELPAY mukofoti (0.4%) o\'tkaziladi ✅'),
    ('group_end',),

    ('group', 'gm', 'Bosqich 2: Har bir agent uchun mukofot hisoblash va to\'lash'),
    ('self', 'be',  'be',  'Har bir agent uchun:\nagent_reward = Σ(txn_summa × 0.2%)'),
    ('msg',  'be',  'db',  'Agent 1 depozitiga mukofot kirim (+0.2%)'),
    ('ret',  'db',  'be',  'Kirim tasdiqlandi ✅'),
    ('msg',  'be',  'pdf', 'Agent 1 hisob-fakturasini generatsiya qil'),
    ('ret',  'pdf', 'be',  'Agent 1 PDF tayyor 📄'),
    ('msg',  'be',  'db',  'Agent 2 depozitiga mukofot kirim (+0.2%)'),
    ('ret',  'db',  'be',  'Kirim tasdiqlandi ✅'),
    ('msg',  'be',  'pdf', 'Agent 2 hisob-fakturasini generatsiya qil'),
    ('ret',  'pdf', 'be',  'Agent 2 PDF tayyor 📄'),
    ('note', '...  (barcha agentlar uchun takrorlanadi)  ...'),
    ('group_end',),

    ('group', 'g5', 'Bosqich 3: Umumiy hisobot va yakunlash'),
    ('msg',  'be',  'pdf', 'OSON–ELPAY umumiy\nhisobotini generatsiya qil'),
    ('ret',  'pdf', 'be',  'Umumiy PDF tayyor 📄'),
    ('self', 'be',  'be',  'Mukofot tranzaksiyalarini\ncashout_rewards jadvaliga yozadi'),
    ('ret',  'be',  'cron','Oylik yopilish yakunlandi ✅'),
    ('group_end',),

    ('note',
     'Komissiya taqsimoti: Mijoz 1% → OSON 0.6% saqlanadi | '
     'OSON → ELPAY 0.4% qaytaradi | ELPAY → Agent 0.2% | ELPAY sof daromad 0.2%'),
]

svg3 = render_svg(
    'Oylik Mukofot Oqimi (Month-End Flow) — Kartadan Naqd Pul Yechish | ELPAY',
    ME_PARTS, ME_MSGS,
    part_w=140, part_h=54, gap=50
)
save('/projects/sandbox/airobo/SEQUENCE_MONTH_END_FLOW.svg', svg3)

print('\nBarchasi tayyor!')
