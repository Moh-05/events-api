<?php

namespace Database\Factories;

use App\Models\VendorProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Blueprint for one fake product/service. Used by HaflatiDemoSeeder.
 * See smart-search.md at the repo root.
 *
 * IMPORTANT — products and services are the SAME table. What separates them is
 * the PARENT vendor's booking_style:
 *   booking_style = 'order'       -> a product (customer pays full price)
 *   booking_style = 'appointment' -> a service (customer pays a 20% deposit)
 *
 * Never use `stock` to tell them apart: a seller's product can also have
 * stock = null (untracked inventory).
 *
 * deposit_percent is deliberately NOT set here. It is not in the model's
 * $fillable — the 20% deposit is a fixed platform rule that comes from the DB
 * default. Adding it to a factory would be silently dropped by mass assignment.
 *
 * @extends Factory<VendorProduct>
 */
class VendorProductFactory extends Factory
{
    protected $model = VendorProduct::class;

    /**
     * Realistic listings per vendor_type: [name, description].
     * The four appointment types sell packages; the six order types sell items.
     */
    private const CATALOG = [
        'photographer' => [
            ['باقة تصوير عرس كاملة', 'تغطية كاملة ليوم العرس من التحضير حتى نهاية الحفل، مصور ومساعد، ألبوم مطبوع 40 صفحة وفيديو مونتاج.'],
            ['باقة تصوير خطوبة', 'جلسة تصوير خطوبة داخل الاستوديو أو خارجي، 100 صورة معدلة وألبوم صغير.'],
            ['جلسة تصوير خارجية', 'جلسة تصوير في موقع خارجي حسب اختياركم، ساعتان تصوير مع تعديل احترافي للصور.'],
            ['تصوير فيديو للعرس', 'تصوير فيديو احترافي بكاميرتين مع مونتاج نهائي ومقطع قصير للسوشيال ميديا.'],
            ['باقة تصوير عيد ميلاد', 'تغطية حفلة عيد ميلاد، تصوير فوتوغرافي مع طباعة فورية للصور.'],
        ],
        'makeupArtist' => [
            ['مكياج عروس كامل', 'مكياج عروس مع تسريحة الشعر والرموش، يشمل تجربة مكياج قبل يوم العرس.'],
            ['مكياج سهرة', 'مكياج سهرة ناعم أو قوي حسب الرغبة مع تسريحة بسيطة.'],
            ['باقة العروس الكاملة', 'عناية بالبشرة قبل العرس، مكياج يوم العرس، تسريحة شعر ومانيكير.'],
            ['تسريحة شعر مناسبات', 'تسريحة شعر للمناسبات والأعراس بتصاميم حديثة تدوم طوال السهرة.'],
        ],
        'dj' => [
            ['باقة دي جي للعرس', 'إحياء حفل الزفاف كامل، نظام صوت وإضاءة، منسق أغاني عربية وأجنبية لخمس ساعات.'],
            ['نظام صوت وإضاءة', 'تأجير نظام صوت وإضاءة ليزر مع فني تشغيل طوال الحفل.'],
            ['باقة زفة ومقدم حفل', 'زفة عريس وعروس مع مقدم حفل وتنسيق كامل لفقرات السهرة.'],
            ['دي جي حفلة خطوبة', 'تغطية موسيقية لحفلة الخطوبة، ثلاث ساعات مع نظام صوت متكامل.'],
        ],
        'weddingHall' => [
            ['حجز صالة ليلة كاملة', 'حجز الصالة لليلة كاملة، تتسع لأربعمئة شخص، تشمل الإضاءة والتكييف وخدمة الضيافة.'],
            ['باقة عرس متكاملة', 'صالة وضيافة وتنسيق زهور وإضاءة، باقة كاملة ليوم العرس.'],
            ['حجز قاعة خطوبة', 'قاعة صغيرة لحفلات الخطوبة تتسع لمئة وخمسين شخص مع ضيافة.'],
            ['باقة الصالة مع البوفيه', 'حجز الصالة مع بوفيه عشاء مفتوح لعدد الضيوف المتفق عليه.'],
        ],
        'flowers' => [
            ['باقة ورد أحمر', 'باقة ورد جوري أحمر طبيعي منسقة بتغليف فاخر، مناسبة للخطوبة والمناسبات.'],
            ['بوكيه عروس', 'بوكيه عروس من الورد الطبيعي بتنسيق حديث يتناسب مع فستان الزفاف.'],
            ['تنسيق سيارة العروس', 'تزيين سيارة العروس بالورد الطبيعي بتصميم حسب الطلب.'],
            ['تنسيق زهور القاعة', 'تنسيق زهور طبيعية لطاولات القاعة والمدخل ومنصة العروسين.'],
            ['باقة ورد أبيض', 'باقة ورد أبيض طبيعي بتنسيق أنيق مع تغليف مميز.'],
        ],
        'gifts' => [
            ['توزيعات عرس', 'توزيعات أعراس بتغليف مميز، الحد الأدنى مئة قطعة مع إمكانية الطباعة.'],
            ['هدية مناسبة مغلفة', 'هدية تذكارية بتغليف فاخر مناسبة للخطوبة والأعراس.'],
            ['توزيعات مولود', 'توزيعات مواليد بألوان وتصاميم حسب الطلب مع بطاقة شكر.'],
            ['صندوق هدايا فاخر', 'صندوق هدايا يحتوي على قطع منوعة بتغليف أنيق.'],
        ],
        'dresses' => [
            ['فستان زفاف تفصيل', 'فستان زفاف مفصل حسب القياس، أقمشة مستوردة وتطريز يدوي، يشمل جلسات القياس.'],
            ['تأجير فستان زفاف', 'تأجير فستان زفاف لليلة العرس مع خدمة التعديل والكي.'],
            ['فستان سهرة', 'فستان سهرة بتصميم حديث متوفر بعدة مقاسات وألوان.'],
            ['فستان خطوبة', 'فستان خطوبة أنيق، تفصيل أو جاهز حسب الطلب.'],
        ],
        'accessories' => [
            ['طقم إكسسوارات عروس', 'طقم كامل للعروس يشمل تاج وعقد وأقراط بتصميم متناسق.'],
            ['تاج عروس', 'تاج عروس مرصع بأحجار كريستال بتصميم كلاسيكي.'],
            ['عقد سهرة', 'عقد أنيق للسهرات والمناسبات بتشطيب ذهبي أو فضي.'],
            ['أقراط مناسبات', 'أقراط بتصاميم متنوعة مناسبة للأعراس والسهرات.'],
        ],
        'candles' => [
            ['شموع ديكور للقاعة', 'مجموعة شموع لتزيين طاولات القاعة، متوفرة بعدة أحجام وألوان.'],
            ['شمعة معطرة', 'شمعة معطرة يدوية الصنع بروائح متنوعة، مناسبة للهدايا والديكور.'],
            ['طقم شموع مناسبات', 'طقم شموع بتصميم خاص للأعراس وحفلات الخطوبة.'],
            ['شموع عيد ميلاد', 'شموع أرقام وأشكال لتزيين كيك عيد الميلاد.'],
        ],
        'cakes' => [
            ['تورتة زفاف ثلاث طبقات', 'تورتة زفاف من ثلاث طبقات بتصميم حسب الطلب، تكفي مئة وخمسين شخص.'],
            ['كيك عيد ميلاد', 'كيك عيد ميلاد بتصميم ونكهة حسب الطلب، متوفر بعدة أحجام.'],
            ['حلويات ضيافة', 'صينية حلويات ضيافة منوعة شرقية وغربية للمناسبات.'],
            ['تورتة خطوبة', 'تورتة خطوبة بطبقتين مع تزيين حسب ألوان الحفل.'],
            ['كيك مناسبات صغير', 'كيك بحجم صغير مناسب للمناسبات العائلية، نكهات متعددة.'],
        ],
    ];

    public function definition(): array
    {
        // A bare ::factory() call with no vendor gets a photographer listing.
        // The seeder always passes the parent vendor's type via forType().
        return $this->listingFor('photographer');
    }

    /**
     * Build a listing that matches the parent vendor's type, and price/stock
     * that match its booking_style.
     */
    public function forType(string $vendorType, string $bookingStyle): static
    {
        return $this->state(fn () => $this->listingFor($vendorType, $bookingStyle));
    }

    /** BAIT: hidden item — must never appear in search results. */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
            'name'         => 'BAIT-unavailable ' . $attributes['name'],
        ]);
    }

    /**
     * BAIT: sold out. In production is_available is auto-set to false the moment
     * stock reaches 0, so a real sold-out row looks exactly like this.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock'        => 0,
            'is_available' => false,
            'name'         => 'BAIT-outofstock ' . $attributes['name'],
        ]);
    }

    private function listingFor(string $vendorType, ?string $bookingStyle = null): array
    {
        [$name, $description] = fake()->randomElement(self::CATALOG[$vendorType]);

        $isOrder = $bookingStyle === 'order';

        return [
            'name'         => $name,
            'description'  => $description,
            'price'        => $this->priceFor($vendorType),
            'is_available' => true,

            // Only order vendors track stock. Appointment services are
            // untracked (null) — all stock logic skips them.
            // Some sellers leave stock untracked too, which is exactly why
            // stock must never be used to tell a product from a service.
            'stock'        => $isOrder ? fake()->optional(0.8)->numberBetween(3, 40) : null,

            'meta'         => $this->metaFor($vendorType),
        ];
    }

    /** Rough SYP ranges per category so price filters have real spread. */
    private function priceFor(string $vendorType): int
    {
        $ranges = [
            'photographer' => [300_000, 2_500_000],
            'makeupArtist' => [150_000, 900_000],
            'dj'           => [400_000, 3_000_000],
            'weddingHall'  => [2_000_000, 15_000_000],
            'flowers'      => [40_000, 400_000],
            'gifts'        => [25_000, 300_000],
            'dresses'      => [500_000, 6_000_000],
            'accessories'  => [50_000, 800_000],
            'candles'      => [20_000, 200_000],
            'cakes'        => [80_000, 900_000],
        ];

        [$min, $max] = $ranges[$vendorType];

        // Rounded to the nearest 5,000 — real listings are not priced at 137,412.
        return (int) (round(fake()->numberBetween($min, $max) / 5000) * 5000);
    }

    /**
     * meta is a free-form JSON column. The AI service embeds its values, so it
     * is extra searchable text — keep it meaningful.
     */
    private function metaFor(string $vendorType): array
    {
        return match ($vendorType) {
            'photographer' => [
                'duration_hours' => fake()->randomElement([2, 4, 6, 8]),
                'includes'       => fake()->randomElement(['ألبوم مطبوع', 'فيديو مونتاج', 'صور معدلة رقمياً']),
            ],
            'makeupArtist' => [
                'includes' => fake()->randomElement(['تسريحة شعر', 'رموش', 'تجربة مكياج مسبقة']),
            ],
            'dj' => [
                'duration_hours' => fake()->randomElement([3, 4, 5]),
                'includes'       => fake()->randomElement(['إضاءة ليزر', 'مقدم حفل', 'زفة']),
            ],
            'weddingHall' => [
                'capacity' => fake()->randomElement([150, 200, 300, 400, 600]),
                'includes' => fake()->randomElement(['ضيافة', 'تكييف', 'موقف سيارات', 'تنسيق زهور']),
            ],
            'dresses' => [
                'sizes' => fake()->randomElement(['S,M,L', 'M,L,XL', 'تفصيل حسب القياس']),
                'color' => fake()->randomElement(['أبيض', 'أوف وايت', 'شامبين', 'أحمر']),
            ],
            'cakes' => [
                'serves'  => fake()->randomElement([20, 50, 100, 150]),
                'flavour' => fake()->randomElement(['شوكولا', 'فانيلا', 'فراولة', 'لوتس']),
            ],
            'flowers' => [
                'color' => fake()->randomElement(['أحمر', 'أبيض', 'وردي', 'مشكل']),
                'type'  => fake()->randomElement(['ورد طبيعي', 'ورد مجفف']),
            ],
            default => [
                'note' => fake()->randomElement(['تغليف هدية متوفر', 'تصميم حسب الطلب', 'توصيل متوفر']),
            ],
        };
    }
}
