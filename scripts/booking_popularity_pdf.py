#!/usr/bin/env python3
"""Aggregate Timma serviceslots into a booking-popularity PDF (weekday/hour)."""
import glob
import json
from collections import Counter
from datetime import datetime
from zoneinfo import ZoneInfo

import matplotlib.pyplot as plt
import numpy as np
from matplotlib.backends.backend_pdf import PdfPages
from matplotlib.colors import LinearSegmentedColormap

TZ = ZoneInfo('Europe/Tallinn')
BLUE = '#2a78d6'
INK = '#0b0b0b'
INK2 = '#52514e'
MUTED = '#898781'
GRID = '#e1e0d9'
SURFACE = '#fcfcfb'
RAMP = ['#fcfcfb', '#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95', '#0d366b']

DAYS = ['Esmasp', 'Teisip', 'Kolmap', 'Neljap', 'Reede', 'Laup', 'Pühap']

starts = []
for f in glob.glob('scripts/data/serviceslots/*.json'):
    for s in json.load(open(f)):
        starts.append(datetime.fromisoformat(s['start'].replace('Z', '+00:00')).astimezone(TZ))

by_day = Counter(d.weekday() for d in starts)
by_hour = Counter(d.hour for d in starts)
heat = np.zeros((7, 24))
for d in starts:
    heat[d.weekday()][d.hour] += 1

hours = range(min(by_hour), max(by_hour) + 1)
first, last = min(starts), max(starts)

plt.rcParams.update({
    'font.family': 'sans-serif',
    'text.color': INK,
    'axes.edgecolor': '#c3c2b7',
    'axes.labelcolor': INK2,
    'xtick.color': MUTED,
    'ytick.color': MUTED,
    'axes.grid': False,
})


def style(ax):
    for side in ('top', 'right', 'left'):
        ax.spines[side].set_visible(False)
    ax.set_facecolor(SURFACE)
    ax.tick_params(length=0)


with PdfPages('scripts/data/exports/booking-popularity.pdf') as pdf:
    fig = plt.figure(figsize=(8.27, 11.69), facecolor=SURFACE)  # A4
    fig.suptitle('Yumefit — broneeringute populaarsus päeva ja kellaaja järgi', fontsize=16,
                 fontweight='bold', x=0.07, ha='left', y=0.965)
    fig.text(0.07, 0.935, f'{len(starts)} broneeringut (Timma andmed), '
             f'{first:%d.%m.%Y} – {last:%d.%m.%Y} (Eesti aja järgi)',
             fontsize=9, color=INK2)

    ax1 = fig.add_axes([0.07, 0.68, 0.86, 0.20])
    vals = [by_day[i] for i in range(7)]
    bars = ax1.bar(DAYS, vals, color=BLUE, width=0.6)
    peak = max(range(7), key=lambda i: vals[i])
    bars[peak].set_color('#184f95')
    for b, v in zip(bars, vals):
        ax1.text(b.get_x() + b.get_width() / 2, v + max(vals) * 0.02, str(v),
                 ha='center', fontsize=8, color=INK2)
    ax1.set_title('Broneeringud nädalapäeviti', loc='left', fontsize=11, fontweight='bold', color=INK)
    ax1.yaxis.set_visible(False)
    style(ax1)

    ax2 = fig.add_axes([0.07, 0.38, 0.86, 0.20])
    hvals = [by_hour.get(h, 0) for h in hours]
    hbars = ax2.bar([f'{h:02d}' for h in hours], hvals, color=BLUE, width=0.7)
    hpeak = max(range(len(hvals)), key=lambda i: hvals[i])
    hbars[hpeak].set_color('#184f95')
    for b, v in zip(hbars, hvals):
        if v:
            ax2.text(b.get_x() + b.get_width() / 2, v + max(hvals) * 0.02, str(v),
                     ha='center', fontsize=7, color=INK2)
    ax2.set_title('Broneeringud algustunni järgi', loc='left', fontsize=11, fontweight='bold', color=INK)
    ax2.set_xlabel('Kellaaeg', fontsize=9)
    ax2.yaxis.set_visible(False)
    style(ax2)

    ax3 = fig.add_axes([0.07, 0.07, 0.86, 0.24])
    hmat = heat[:, min(hours):max(hours) + 1]
    cmap = LinearSegmentedColormap.from_list('seq_blue', RAMP)
    im = ax3.imshow(hmat, cmap=cmap, aspect='auto')
    ax3.set_yticks(range(7), DAYS, fontsize=8)
    ax3.set_xticks(range(len(list(hours))), [f'{h:02d}' for h in hours], fontsize=8)
    for i in range(7):
        for j in range(hmat.shape[1]):
            v = int(hmat[i, j])
            if v:
                ax3.text(j, i, v, ha='center', va='center', fontsize=6.5,
                         color='#ffffff' if v > hmat.max() * 0.55 else INK)
    ax3.set_title('Soojuskaart: nädalapäev × kellaaeg', loc='left', fontsize=11, fontweight='bold', color=INK)
    ax3.set_xlabel('Kellaaeg', fontsize=9)
    for side in ax3.spines.values():
        side.set_visible(False)
    ax3.tick_params(length=0)

    pdf.savefig(fig)
    plt.close(fig)

top_cells = sorted(((int(heat[d, h]), d, h) for d in range(7) for h in range(24)), reverse=True)[:5]
print(f'{len(starts)} bookings, peak day {DAYS[peak]} ({vals[peak]}), peak hour {list(hours)[hpeak]:02d}:00')
print('top slots:', ', '.join(f'{DAYS[d]} {h:02d}:00 ({v})' for v, d, h in top_cells))
