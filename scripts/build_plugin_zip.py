#!/usr/bin/env python3
import argparse
import pathlib
import zipfile

p = argparse.ArgumentParser()
p.add_argument("slug")
p.add_argument("--out", default="dist")
a = p.parse_args()

root = pathlib.Path(__file__).resolve().parents[1]
src = root / "plugins" / a.slug
if not src.exists():
    raise SystemExit(f"Unknown plugin: {a.slug}")
if not any(src.glob("*.php")):
    raise SystemExit(f"{a.slug} has no plugin PHP source yet")

out = root / a.out
out.mkdir(exist_ok=True)
zip_path = out / f"{a.slug}.zip"

with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as z:
    for f in src.rglob("*"):
        if f.is_file():
            z.write(f, pathlib.Path(a.slug) / f.relative_to(src))

print(zip_path)
