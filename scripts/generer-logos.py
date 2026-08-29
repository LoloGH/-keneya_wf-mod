"""Derive les fichiers de logo servis par l'application.

    python3 scripts/generer-logos.py

Les deux fichiers d'origine, fournis par le porteur du projet, sont dans
`public/images/*-source.svg`. Ce ne sont pas des dessins vectoriels : chacun
n'est qu'une image matricielle encodee en base64 dans une balise <image> —
506 Ko pour le logo complet, 1,3 Mo pour le monogramme. Les servir tels quels
ferait passer 1,8 Mo sur le reseau de l'hopital au premier chargement.

On en tire donc, une fois pour toutes et en les versionnant :

  - les deux logos aux dimensions reellement affichees ;
  - leur declinaison pour les fonds sombres, ou le bleu nuit serait invisible ;
  - une version aplatie sur du blanc pour les documents imprimes ;
  - le favicon, l'icone d'ecran d'accueil et le .ico multi-tailles.

A relancer si le porteur du projet fournit de nouveaux fichiers d'origine.
Depend de Pillow et de numpy, qui ne sont pas des dependances de
l'application : `pip install pillow numpy`.
"""
import base64, io, os, re
from PIL import Image
import numpy as np

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(RACINE, 'public')
SRC = os.path.join(OUT, 'images')

VERT_CLAIR = (0x5F, 0xD3, 0xAA)


def charge(svg):
    s = open(svg, encoding='utf-8').read()
    b64 = re.search(r'href="data:image/png;base64,([^"]+)"', s).group(1)
    return Image.open(io.BytesIO(base64.b64decode(b64))).convert('RGBA')


def rogne(im, marge=0):
    box = im.getchannel('A').getbbox()
    if marge:
        box = (max(0, box[0] - marge), max(0, box[1] - marge),
               min(im.width, box[2] + marge), min(im.height, box[3] + marge))
    return im.crop(box)


def eclaircit(im):
    """Decline le logo pour les fonds sombres : le bleu nuit passe au blanc, le
    vert a un vert plus clair.

    Deux precautions, apprises en regardant le resultat :

    - le passage bleu -> vert du W est un degrade ; un seuil net y laissait un
      bord en dents de scie. La teinte est donc melangee progressivement.
    - les trois pastilles qui prolongent l'arc s'effacent par transparence, pas
      par la couleur. Posees telles quelles sur un fond sombre, elles viraient
      au gris. L'opacite est relevee (alpha ^ 0.62) pour qu'elles restent des
      pastilles claires qui s'estompent.
    """
    a = np.array(im).astype(np.float32)
    t = np.clip((a[:, :, 1] - a[:, :, 2] + 10) / 40, 0, 1)[:, :, None]
    couleur = 255 * (1 - t) + np.array(VERT_CLAIR, dtype=np.float32) * t
    alpha = 255 * np.power(a[:, :, 3:4] / 255, 0.62)
    return Image.fromarray(
        np.concatenate([couleur, alpha], axis=2).round().clip(0, 255).astype(np.uint8), 'RGBA')


def redimensionne(im, largeur):
    h = round(im.height * largeur / im.width)
    return im.resize((largeur, h), Image.LANCZOS)


def carre(im):
    cote = max(im.size)
    fond = Image.new('RGBA', (cote, cote), (0, 0, 0, 0))
    fond.paste(im, ((cote - im.width) // 2, (cote - im.height) // 2))
    return fond


def ecrit(im, chemin):
    im.save(chemin, 'PNG', optimize=True)
    print(f'{chemin:60s} {im.size[0]}x{im.size[1]:<5d} {os.path.getsize(chemin) // 1024} Ko')


logo = rogne(charge(f'{SRC}/keneya-logo-source.svg'))
icone = rogne(charge(f'{SRC}/keneya-icone-source.svg'))
print('sources rognees :', logo.size, icone.size)

ecrit(redimensionne(logo, 900), f'{OUT}/images/keneya-logo.png')
ecrit(redimensionne(eclaircit(logo), 900), f'{OUT}/images/keneya-logo-clair.png')
ecrit(redimensionne(icone, 512), f'{OUT}/images/keneya-icone.png')
ecrit(redimensionne(eclaircit(icone), 512), f'{OUT}/images/keneya-icone-claire.png')

# Version pour les documents imprimes. Deux differences avec celle des ecrans :
# elle est petite, et elle est aplatie sur du blanc. dompdf stocke la couche
# alpha d'un PNG dans un masque separe, qu'il ne compresse pas : le monogramme
# des ecrans, transparent et cinq fois plus grand, ajoutait une centaine de
# kilo-octets a chaque ordonnance PDF. Le papier etant blanc, la transparence
# n'y sert a rien.
impression = redimensionne(icone, 200)
blanc = Image.new('RGB', impression.size, (255, 255, 255))
blanc.paste(impression, mask=impression.getchannel('A'))
blanc.save(f'{OUT}/images/keneya-icone-impression.png', 'PNG', optimize=True)
print(f'{OUT}/images/keneya-icone-impression.png', blanc.size,
      os.path.getsize(f'{OUT}/images/keneya-icone-impression.png') // 1024, 'Ko')

# Icones de l'onglet et de l'ecran d'accueil : le monogramme centre dans un carre.
ic = carre(icone)
ecrit(redimensionne(ic, 180), f'{OUT}/apple-touch-icon.png')
ic.resize((48, 48), Image.LANCZOS).save(
    f'{OUT}/favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)])
print(f'{OUT}/favicon.ico', os.path.getsize(f"{OUT}/favicon.ico"), 'octets')

# favicon.svg : les navigateurs le preferent, mais le monogramme fourni n'est pas
# vectoriel. On garde donc un SVG, avec a l'interieur un PNG de 128 px — quelques
# kilo-octets au lieu du 1,3 Mo de l'original.
tampon = io.BytesIO()
redimensionne(ic, 128).save(tampon, 'PNG', optimize=True)
b64 = base64.b64encode(tampon.getvalue()).decode()
open(f'{OUT}/favicon.svg', 'w', encoding='utf-8').write(
    '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">'
    '<title>KƐNƐYA WorkFlow</title>'
    f'<image width="128" height="128" href="data:image/png;base64,{b64}"/></svg>\n')
print(f'{OUT}/favicon.svg', os.path.getsize(f"{OUT}/favicon.svg") // 1024, 'Ko')
