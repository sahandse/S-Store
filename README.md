# فروشگاه افزونه اس — S Store

ساختار مرکزی افزونه‌های وردپرس Sahand Rezvan.

- `plugins/<slug>/` سورس مستقل هر افزونه
- `shared/design-system.css` دیزاین سیستم مشترک پنل‌های ادمین
- `manifest/plugins.json` منبع نسخه‌ها و لینک ZIP برای سیستم بروزرسانی S Store
- `.github/workflows/release-plugin.yml` ساخت ZIP و Release هر افزونه به صورت مستقل

## انتشار نسخه جدید

1. سورس افزونه را فقط در فولدر خودش تغییر دهید.
2. Version هدر افزونه را افزایش دهید.
3. Workflow `Release WordPress Plugin` را اجرا کنید و `slug` و `version` را وارد کنید.
4. Workflow فقط همان افزونه را ZIP می‌کند، Release می‌سازد و Manifest را به‌روز می‌کند.
5. وردپرس‌های دارای S Store بعد از بررسی بروزرسانی، نسخه جدید همان افزونه را مشاهده می‌کنند.

نکته: فایل ZIP هر افزونه باید در ریشه فقط فولدر همان slug را داشته باشد؛ اسکریپت build همین ساختار را تضمین می‌کند.
