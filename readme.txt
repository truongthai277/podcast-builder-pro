=== Podcast Builder Pro ===
Contributors: yourname
Tags: podcast, rss feed, text-to-speech, seo, backlink
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress biến bài viết thành podcast tự động — AI script, TTS audio, RSS feed chuẩn iTunes, show notes có backlink.

== Description ==

**Podcast Builder Pro** giúp bạn biến mỗi bài viết WordPress thành một tập podcast chuyên nghiệp với quy trình tự động:

`Blog Post → AI Script → TTS Audio → RSS Feed → Directory → Backlink`

=== Tính năng chính ===

* 🤖 **AI Script Generator** — Google Gemini / OpenAI GPT / OpenRouter tạo kịch bản hội thoại 2 giọng tự nhiên
* 🎙️ **Text-to-Speech** — Hỗ trợ OpenAI TTS, Google Cloud TTS, ElevenLabs, Fish Audio, FPT.AI, YEScale
* 📡 **RSS Feed iTunes** — Tự động sinh RSS feed chuẩn Apple Podcasts
* 🔗 **Show Notes + Backlink** — Tự động chèn backlink với URL và anchor text tùy chỉnh
* 📋 **Directory Tracker** — Quản lý 11+ podcast directories, trạng thái submit, profile URL
* ⚡ **Auto-Generate Queue** — WP Cron tự đồng bộ bài viết và tạo podcast theo lịch
* 🔑 **License System** — Kích hoạt theo domain, grace period 7 ngày

=== Podcast Directories hỗ trợ ===

Apple Podcasts (DA 100), Spotify (DA 93), Google Podcasts (DA 94), Amazon Music (DA 88),
TuneIn (DA 91), iHeartRadio (DA 82), Pocket Casts (DA 72), Podchaser (DA 67),
Listen Notes (DA 68), Podcast Index (DA 71), Castbox (DA 57)

== Installation ==

1. Upload thư mục `podcast-builder-pro` vào `/wp-content/plugins/`
2. Kích hoạt plugin tại trang Plugins > Installed Plugins
3. Vào **Podcast Builder > License** → nhập license key và kích hoạt
4. Vào **Podcast Builder > Settings** → cấu hình API key AI và TTS
5. Mở bất kỳ bài viết nào → nhấn **Generate Podcast** trong meta box

== Frequently Asked Questions ==

= Plugin có tự động đăng ký lên Apple Podcasts, Spotify không? =

Không. Plugin tạo RSS feed chuẩn iTunes. Bạn chỉ cần submit RSS feed lên từng platform 1 lần duy nhất.
Sau đó, mỗi tập mới sẽ tự động xuất hiện trên tất cả platforms đã đăng ký.

= Cần API key gì để dùng? =

Bạn cần ít nhất 1 AI API key (Gemini/OpenAI/OpenRouter) và 1 TTS API key.
Plugin cũng hỗ trợ upload audio thủ công nếu không muốn dùng TTS.

= Plugin hoạt động với hosting giá rẻ không? =

Có. Yêu cầu tối thiểu: PHP 7.4+, WordPress 6.0+, WP-Cron hoạt động bình thường.

= Backlink từ podcast có giúp SEO không? =

Có. Các nền tảng podcast như Apple, Spotify có DA 90+. Show notes chứa backlink về site bạn
là backlink white-hat, hoàn toàn tự nhiên.

= Có tương thích với RankMath không? =

Có. Nếu có RankMath PRO với Podcast module, plugin tự động sync dữ liệu vào RankMath.

== Changelog ==

= 1.0.1 =
* **[Fix]** Lỗi cài đặt (activation hook): `PBP_Directory::seed_defaults()` gọi khi class chưa được load — plugin không thể kích hoạt được
* **[Fix]** RSS feed URL không trả về XML — lỗi logic kiểm tra query var `pbp_feed`
* **[Fix]** `sanitize()` trong PBP_Settings cắt mất xuống dòng trong trường `podcast_desc` (textarea)
* **[Improvement]** `activate()` tự load class cần thiết trước khi chạy để tránh fatal error

= 1.0.0 =
* Release lần đầu
* AI Script: Gemini, OpenAI GPT, OpenRouter
* TTS: OpenAI, Google Cloud, ElevenLabs, Fish Audio, FPT.AI, YEScale
* RSS Feed chuẩn iTunes với đầy đủ namespace
* Directory Tracker: 11 platforms
* Auto-Queue với WP Cron
* License activation system
* Meta box trong mỗi bài viết
* Admin UI: Dashboard, Episodes, Feed, Directory, Queue, Settings, License

== Upgrade Notice ==

= 1.0.1 =
Bugfix quan trọng: sửa lỗi không thể cài đặt/kích hoạt plugin, RSS feed không hoạt động — nâng cấp ngay.

= 1.0.0 =
Initial release.
