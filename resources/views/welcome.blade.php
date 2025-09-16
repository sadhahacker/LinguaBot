<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON Translator</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Heroicons for icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/heroicons@2.0.18/24/outline/style.css">
    <!-- Inter font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-effect {
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            background-color: rgba(255, 255, 255, 0.75);
            border: 1px solid rgba(209, 213, 219, 0.3);
        }
        .dark-glass {
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            background-color: rgba(17, 24, 39, 0.8);
            border: 1px solid rgba(75, 85, 99, 0.3);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .json-textarea:focus {
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .pulse-ring {
            animation: pulse-ring 1.5s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }
        @keyframes pulse-ring {
            0% {
                transform: scale(.33);
            }
            40%, 50% {
                opacity: 1;
            }
            100% {
                opacity: 0;
                transform: scale(1.03);
            }
        }
    </style>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'gradient': 'gradient 15s ease infinite',
                        'float': 'float 6s ease-in-out infinite',
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50">

<!-- Animated background elements -->
<div class="fixed inset-0 overflow-hidden pointer-events-none">
    <div class="absolute -top-4 -right-4 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-float"></div>
    <div class="absolute -bottom-8 -left-4 w-72 h-72 bg-indigo-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-float" style="animation-delay: 2s"></div>
    <div class="absolute top-1/2 left-1/2 w-72 h-72 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-float" style="animation-delay: 4s"></div>
</div>

<div class="relative z-10 container mx-auto px-4 py-8 max-w-7xl">

    <!-- Header -->
    <div class="glass-effect rounded-2xl p-8 mb-8 shadow-xl border">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <div class="w-12 h-12 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                        </svg>
                    </div>
                    <div class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl blur opacity-30 pulse-ring"></div>
                </div>
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                        JSON Translator
                    </h1>
                    <p class="text-gray-600 mt-1">Transform your JSON data across languages with AI precision</p>
                </div>
            </div>
            <div class="hidden md:flex items-center space-x-2">
                <div class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Online</div>
                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
            </div>
        </div>

        <!-- Translation Controls -->
        <div class="flex flex-wrap items-end gap-4">
            <!-- From Language -->
            <div class="flex-1 min-w-0">
                <label for="from-lang" class="block text-sm font-semibold text-gray-700 mb-2">From Language</label>
                <select id="from-lang" class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200 appearance-none cursor-pointer hover:bg-white">
                    <option value="">Select language...</option>
                </select>
            </div>

            <!-- Swap Button -->
            <div class="flex-shrink-0">
                <button id="swap-languages" class="p-3 bg-white/80 border border-gray-200 rounded-xl hover:bg-indigo-50 hover:border-indigo-300 focus:ring-4 focus:ring-indigo-100 transition-all duration-200 group">
                    <svg class="w-5 h-5 text-gray-600 group-hover:text-indigo-600 transition-colors duration-200 group-hover:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                    </svg>
                </button>
            </div>

            <!-- To Language -->
            <div class="flex-1 min-w-0">
                <label for="to-lang" class="block text-sm font-semibold text-gray-700 mb-2">To Language</label>
                <select id="to-lang" class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200 appearance-none cursor-pointer hover:bg-white">
                    <option value="">Select language...</option>
                </select>
            </div>

            <!-- Translate Button -->
            <div class="flex-shrink-0">
                <button id="translate-btn" class="px-8 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold rounded-xl hover:from-indigo-600 hover:to-purple-700 focus:ring-4 focus:ring-indigo-200 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <span>Translate</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Alert messages -->
    <div id="alert-box" class="mb-6"></div>

    <!-- JSON Areas -->
    <div class="grid lg:grid-cols-2 gap-8">

        <!-- Source JSON -->
        <div class="glass-effect rounded-2xl overflow-hidden shadow-xl border">
            <div class="px-6 py-4 bg-gradient-to-r from-indigo-500/10 to-purple-500/10 border-b border-gray-200/50">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                        <div class="w-3 h-3 bg-indigo-500 rounded-full"></div>
                        <span>Source JSON</span>
                    </h3>
                    <span id="source-count" class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm font-medium">0 characters</span>
                </div>
            </div>
            <div class="p-6">
                    <textarea
                        id="source-json"
                        class="w-full h-96 p-4 bg-gray-50/50 border border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200 json-textarea font-mono text-sm resize-none"
                        placeholder='{
  "greeting": "Hello",
  "welcome_message": "Welcome to our platform",
  "buttons": {
    "submit": "Submit",
    "cancel": "Cancel"
  }
}'></textarea>
            </div>
        </div>

        <!-- Translated JSON -->
        <div class="glass-effect rounded-2xl overflow-hidden shadow-xl border">
            <div class="px-6 py-4 bg-gradient-to-r from-green-500/10 to-emerald-500/10 border-b border-gray-200/50">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                        <span>Translated JSON</span>
                    </h3>
                    <span id="result-count" class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">0 characters</span>
                </div>
            </div>
            <div class="p-6">
                    <textarea
                        id="result-json"
                        class="w-full h-96 p-4 bg-gray-50/50 border border-gray-200 rounded-xl font-mono text-sm resize-none cursor-default"
                        readonly
                        placeholder="Translated JSON will appear here..."></textarea>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="mt-12 text-center">
        <p class="text-gray-500 text-sm">
            Powered by advanced translation algorithms •
            <span class="text-indigo-600 font-medium">Fast & Accurate</span>
        </p>
    </div>
</div>

<!-- jQuery for compatibility -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
    $(function () {
        $.get('{{ url('language/list') }}', function (languages) {
        const fromSelect = $('#from-lang');
        const toSelect = $('#to-lang');
        fromSelect.empty(); toSelect.empty();

        languages.forEach(lang => {
            const opt = $('<option>').val(lang.code).text(lang.name);
            fromSelect.append(opt.clone());
            toSelect.append(opt.clone());
        });

        fromSelect.val('en');
        toSelect.val('es');
        });

        // Swap languages
        $('#swap-languages').on('click', function () {
            const from = $('#from-lang').val();
            const to = $('#to-lang').val();
            $('#from-lang').val(to);
            $('#to-lang').val(from);

            // Add visual feedback
            $(this).addClass('rotate-180');
            setTimeout(() => $(this).removeClass('rotate-180'), 300);
        });

        // Character counters
        $('#source-json, #result-json').on('input', function () {
            updateCharacterCounts();
        });

        function updateCharacterCounts() {
            const sourceLength = $('#source-json').val().length;
            const resultLength = $('#result-json').val().length;
            $('#source-count').text(sourceLength + ' character' + (sourceLength !== 1 ? 's' : ''));
            $('#result-count').text(resultLength + ' character' + (resultLength !== 1 ? 's' : ''));
        }

        // Translate
        $('#translate-btn').on('click', function () {
            const jsonText = $('#source-json').val().trim();
            const from = $('#from-lang').val();
            const to = $('#to-lang').val();

            if (!jsonText) return showAlert('Please enter JSON to translate', 'warning');
            if (!from || !to) return showAlert('Please select both source and target languages', 'warning');

            try {
                JSON.parse(jsonText); // validate JSON
            } catch (e) {
                return showAlert('Invalid JSON format. Please check your syntax and try again.', 'error');
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html(`
                    <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Translating...</span>
                `);

            $.ajax({
                url: '{{ url('translate') }}',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ json: jsonText, from, to, _token: '{{ csrf_token() }}' }),
                    success: function (res) {
                        $('#result-json').val(JSON.stringify(res, null, 2));
                        updateCharacterCounts();
                        showAlert('Translation completed successfully!', 'success');
                    },
                    error: function () {
                        showAlert('Translation failed. Please try again.', 'error');
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html(`
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span>Translate</span>
                        `);
                    }
                });

        });

        // Helper functions
        function getLanguageName(code) {
            const lang = languages.find(l => l.code === code);
            return lang ? lang.name : code;
        }

        function translateObjectValues(obj, from, to) {
            // Mock translation logic - replace with actual translation
            if (typeof obj === 'string') {
                return `[${to.toUpperCase()}] ${obj}`;
            } else if (Array.isArray(obj)) {
                return obj.map(item => translateObjectValues(item, from, to));
            } else if (typeof obj === 'object' && obj !== null) {
                const result = {};
                for (const [key, value] of Object.entries(obj)) {
                    result[key] = translateObjectValues(value, from, to);
                }
                return result;
            }
            return obj;
        }

        function showAlert(message, type) {
            const alertClass = {
                'success': 'bg-green-50 text-green-800 border-green-200',
                'error': 'bg-red-50 text-red-800 border-red-200',
                'warning': 'bg-yellow-50 text-yellow-800 border-yellow-200',
                'info': 'bg-blue-50 text-blue-800 border-blue-200'
            };

            const iconPath = {
                'success': 'M5 13l4 4L19 7',
                'error': 'M6 18L18 6M6 6l12 12',
                'warning': 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 14.5c-.77.833.192 2.5 1.732 2.5z',
                'info': 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
            };

            $('#alert-box').html(`
                    <div class="glass-effect rounded-xl p-4 border ${alertClass[type]} shadow-lg transform transition-all duration-300 ease-out scale-95 opacity-0" id="current-alert">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${iconPath[type]}"></path>
                            </svg>
                            <p class="font-medium">${message}</p>
                            <button type="button" class="ml-auto flex-shrink-0 p-1 rounded-lg hover:bg-black/10 transition-colors" onclick="$('#current-alert').fadeOut()">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `);

            // Animate in
            setTimeout(() => {
                $('#current-alert').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
            }, 10);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                $('#current-alert').fadeOut();
            }, 5000);
        }

        // Initialize character counts
        updateCharacterCounts();
    });
</script>

</body>
</html>
