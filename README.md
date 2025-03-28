# Expense Tracker Telegram Bot

A Laravel-based Telegram bot that helps users track their expenses by analyzing receipts, voice messages, and text descriptions. The bot automatically categorizes expenses and records them in a personalized Google Spreadsheet.

## Features

- **Multi-format Expense Recording**: Users can submit expenses via:
  - Voice messages (automatically transcribed)
  - Photos of receipts (OCR-processed)
  - Text descriptions
- **AI-powered Analysis**: Automatically extracts expense amounts and categories
- **Google Sheets Integration**: Each user gets a personal expense tracking spreadsheet
- **Real-time Notifications**: Instant Telegram notifications about recorded expenses
- **Automatic Calculations**: The spreadsheet automatically calculates totals and provides expense analytics

## How It Works

1. User registers with the bot via the `/start` command
2. The bot creates a personalized Google Spreadsheet and sends a link to the user
3. User submits expenses in any supported format (voice, photo, text)
4. The bot processes the input:
   - Voice messages are transcribed to text
   - Photos are processed with OCR to extract receipt data
   - Text is analyzed directly
5. AI processes the text to extract expense amount and category
6. Data is recorded in the user's spreadsheet
7. User receives a confirmation notification with expense details

## Installation

### Prerequisites

- PHP 8.4
- Laravel 10
- Composer
- MySQL/PostgreSQL
- Telegram Bot API Token
- Google API credentials
- OpenAI API key (or other AI service for analysis)

### Setup Steps

1. **Clone the repository**

```bash
git clone https://github.com/Kowalski1304/telegram-bot-nez.git
cd telegram-bot-nez
```

2. **Install dependencies**

```bash
composer install
npm install
npm run build
```

3. **Configure environment variables**

```bash
cp .env.example .env
php artisan key:generate
```

Edit the `.env` file to include:
```
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
TELEGRAM_WEBHOOK_URL=your_webhook_url

OPENAI_API_KEY=your_openai_api_key
```

4. **Set up the database**

```bash
php artisan migrate
```

5. **Register webhook with Telegram**

```bash
php artisan telegram:webhook
```

6. **Start the server**

```bash
php artisan serve
```

For production, configure your web server (Nginx/Apache) to point to the public directory.

## Configuration

### Telegram Bot Setup

1. Create a new bot via [@BotFather](https://t.me/BotFather) on Telegram
2. Get your bot token and add it to the `.env` file
3. Set the webhook URL to your server endpoint

### Google Sheets API Setup

1. Create a Google Cloud project
2. Enable Google Sheets API
3. Create service account credentials
4. Download the credentials JSON file
5. Create a template spreadsheet for expense tracking
6. Share the template with the service account email

### AI Service Configuration

Configure your preferred AI service (OpenAI, etc.) in the `config/services.php` file.

## Usage

1. Start a chat with your bot on Telegram
2. Send the `/start` command to register
3. Follow the link to access your personal expense spreadsheet
4. Submit expenses in any of these formats:
   - Voice message describing what you bought and how much it cost
   - Photo of a receipt
   - Text message with expense details

## Project Structure

```
app/
├── Console/           # Console commands
├── DTO/               # Data Transfer Objects
│   └── ExpenseDTO.php
├── Exceptions/        # Custom exceptions
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php
│   │   └── TelegramBotController.php
│   └── Middleware/
│       └── Kernel.php
├── Models/
│   ├── Expense.php
│   └── User.php
├── Providers/         # Service providers
├── Services/
│   ├── Expense/
│   │   └── ExpenseService.php
│   ├── Google/
│   │   └── GoogleService.php
│   ├── OpenAi/
│   │   └── OpenAiService.php
│   └── Telegram/
│       ├── TelegramClient.php
│       ├── TelegramMediaService.php
│       └── TelegramMessageHandler.php
bootstrap/            # Application bootstrap files
config/               # Configuration files
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgements

- [Laravel](https://laravel.com)
- [Laravel Telegram Bot SDK](https://github.com/telegram-bot-sdk/telegram-bot-sdk)
- [Google API Client](https://github.com/googleapis/google-api-php-client)
- [OpenAI API](https://openai.com/api/)
