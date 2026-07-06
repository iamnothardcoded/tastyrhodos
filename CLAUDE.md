# CLAUDE.md — Design Branch

This is the **`design` branch** of the TastyIgniter fork `iamnothardcoded/tastyrhodos`.
It exists for **frontend / visual design work only** — the customer-facing ordering theme.

## What you edit here

| What | Path |
|------|------|
| Blade views (theme markup) | `extensions/jamasa/core/resources/views/igniter-orange/` |
| CSS | `extensions/jamasa/core/resources/css/` |

**Do NOT touch backend logic** in `extensions/jamasa/core/src/` (ordering-state, pause,
API controllers). That's the main dev machine's lane — editing it here creates merge
conflicts when this branch folds back into `4.x`.

## Running the live preview (this machine)

```bash
docker compose -f docker-compose.dev.yml up -d
open http://localhost:8080
```

This pulls the published TastyIgniter image and mounts your theme
(`extensions/jamasa`) on top. Database + Redis run as their own containers.

⚠️ **After editing any Blade/CSS, changes DON'T show until you restart the app**
(the image ships `opcache.validate_timestamps=0`):

```bash
docker compose -f docker-compose.dev.yml restart app
```

Use `restart app` — **never** `down` (that can wipe the DB volume).

## First-time data setup

The DB starts empty. To design against the real Rhodos menu/images, import the
database dump + storage archive exported from the main dev machine (see the setup
notes you were given), then `restart app`. With a full import you do **not** need to
run the interactive `igniter:install`.

## Workflow

```bash
git add -A && git commit -m "design: <what changed>"
git push            # -> origin/design
```

Merging `design` → `4.x` happens on the main dev machine, not here.
