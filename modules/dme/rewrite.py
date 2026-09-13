import os, re

ROOTS = ['src', 'routes', 'database', 'tests', 'resources/views', 'config']
EXTS = ('.php',)

ROUTE_EXACT = ['login', 'logout', 'dashboard', 'search']
ROUTE_PREFIX = ['api', 'appointments', 'audit', 'care-orders', 'consultations', 'documents',
                'hospitalizations', 'imaging', 'laboratory', 'notifications', 'nursing',
                'patients', 'prescriptions', 'record', 'settings', 'sms', 'users']

VIEW_ROOTS = ['appointments', 'audit', 'auth', 'consultations', 'dashboard', 'documents',
              'hospitalizations', 'imaging', 'laboratory', 'layouts', 'notifications',
              'partials', 'patients', 'pdf', 'prescriptions', 'search', 'settings', 'sms', 'users']

COMPONENTS = ['empty-state', 'field-error', 'icon', 'medical-alerts', 'page-header',
              'patient-header', 'sparkline', 'status-badge', 'timeline', 'vital-card']

route_re = re.compile(
    r"route\(\s*'(" + "|".join(ROUTE_EXACT) + r"|(?:" + "|".join(ROUTE_PREFIX) + r")\.[a-z0-9_.\-]+)'"
)
view_re = re.compile(
    r"(view|render|loadView)\(\s*'((?:" + "|".join(VIEW_ROOTS) + r")(?:\.[a-zA-Z0-9_.\-]*)?)'"
)
blade_re = re.compile(
    r"@(extends|include|includeIf|includeWhen|includeUnless|each)\(\s*'((?:"
    + "|".join(VIEW_ROOTS) + r")(?:\.[a-zA-Z0-9_.\-]*)?)'"
)
comp_open_re = re.compile(r"<x-(" + "|".join(COMPONENTS) + r")\b")
comp_close_re = re.compile(r"</x-(" + "|".join(COMPONENTS) + r")>")


def rewrite(text: str) -> str:
    # --- Espaces de noms ---
    text = re.sub(r"\bnamespace App(?=[;\])", "namespace Keneya\\Dme", text)
    text = re.sub(r"\buse App\\", "use Keneya\\Dme\\\\", text)
    text = re.sub(r"(?<![A-Za-z0-9_\])\App\\", "\\Keneya\\Dme\\\\", text)
    text = text.replace("'App\\Models\\\\", "'Keneya\\Dme\\Models\\\\")

    for sub in ('Seeders', 'Factories'):
        text = re.sub(r"\bnamespace Database\\" + sub + r"(?=[;\])",
                      "namespace Keneya\\Dme\\Database\\\\" + sub, text)
        text = re.sub(r"\buse Database\\" + sub + r"\\",
                      "use Keneya\\Dme\\Database\\\\" + sub + "\\\\", text)
        text = re.sub(r"(?<![A-Za-z0-9_\])\Database\\" + sub + r"\\",
                      "\\Keneya\\Dme\\Database\\\\" + sub + "\\\\", text)

    text = re.sub(r"\bnamespace Tests(?=[;\])", "namespace Keneya\\Dme\\Tests", text)
    text = re.sub(r"\buse Tests\\", "use Keneya\\Dme\\Tests\\\\", text)

    # --- Clés de configuration ---
    text = re.sub(r"config\(\s*(\[?\s*)(['\"])keneya\.", lambda m: f"config({m.group(1)}{m.group(2)}dme.", text)
    text = re.sub(r"config\(\s*(\[?\s*)(['\"])sms\.", lambda m: f"config({m.group(1)}{m.group(2)}dme.sms.", text)

    # --- Vues et composants ---
    text = view_re.sub(lambda m: f"{m.group(1)}('dme::{m.group(2)}'", text)
    text = blade_re.sub(lambda m: f"@{m.group(1)}('dme::{m.group(2)}'", text)
    text = comp_open_re.sub(lambda m: f"<x-dme::{m.group(1)}", text)
    text = comp_close_re.sub(lambda m: f"</x-dme::{m.group(1)}>", text)

    # --- Noms de routes ---
    text = route_re.sub(lambda m: f"route('dme.{m.group(1)}'", text)

    return text


changed = 0
for root in ROOTS:
    for dirpath, _dirs, files in os.walk(root):
        for name in files:
            if not name.endswith(EXTS):
                continue
            path = os.path.join(dirpath, name)
            src = open(path, encoding='utf-8').read()
            out = rewrite(src)
            if out != src:
                open(path, 'w', encoding='utf-8', newline='').write(out)
                changed += 1
print(f"fichiers modifiés : {changed}")
