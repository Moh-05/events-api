<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Blueprint for one fake vendor. Used by HaflatiDemoSeeder to build the local
 * demo dataset that Smart Search indexes. See smart-search.md at the repo root.
 *
 * The Arabic content here is deliberately realistic, not fake()->word(). This
 * text is what the AI service actually embeds — random gibberish in a bio means
 * a query like "بدي مصور عرس" matches nothing, and the search looks broken when
 * it is not.
 *
 * LOCAL ONLY. Never run the seeder against Railway.
 *
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /** The four appointment (service) categories — 20% deposit. */
    public const SERVICE_TYPES = ['photographer', 'makeupArtist', 'dj', 'weddingHall'];

    /** The six order (seller) categories — full payment. */
    public const SELLER_TYPES = ['flowers', 'gifts', 'dresses', 'accessories', 'candles', 'cakes'];

    /**
     * Business-name parts per vendor_type, so a photographer is not called
     * "صالة الياسمين". Combined as: prefix + " " + suffix.
     */
    private const NAME_PREFIX = [
        'photographer' => ['استوديو', 'عدسة', 'لقطة', 'ستوديو'],
        'makeupArtist' => ['صالون', 'مركز', 'لمسة', 'ستايل'],
        'dj'           => ['دي جي', 'فرقة', 'صوت', 'نغم'],
        'weddingHall'  => ['صالة', 'قاعة', 'قصر'],
        'flowers'      => ['زهور', 'ورد', 'محل زهور', 'بستان'],
        'gifts'        => ['هدايا', 'متجر هدايا', 'ركن الهدايا'],
        'dresses'      => ['أزياء', 'بيت الأزياء', 'فساتين', 'أتيليه'],
        'accessories'  => ['إكسسوارات', 'مجوهرات', 'ركن'],
        'candles'      => ['شموع', 'عالم الشموع', 'ضوء'],
        'cakes'        => ['حلويات', 'كيك', 'معمل حلويات', 'فرن'],
    ];

    private const NAME_SUFFIX = [
        'الياسمين', 'الشام', 'النور', 'الأمل', 'الوردة', 'اللؤلؤة', 'الماس',
        'السعادة', 'الأصالة', 'الفجر', 'الربيع', 'الزهراء', 'بلقيس', 'ميلاد',
    ];

    /**
     * Bios per type — several variants each so 40 vendors do not share one line.
     * Written the way a Syrian vendor would actually describe his own work.
     */
    private const BIOS = [
        'photographer' => [
            'تصوير أعراس وخطوبة بأسلوب احترافي، خبرة أكثر من عشر سنوات في تغطية حفلات الزفاف.',
            'مصور محترف متخصص بتصوير المناسبات والأعراس، جلسات تصوير خارجية وداخلية بأحدث الكاميرات.',
            'نوثق أجمل لحظاتكم، تصوير فوتوغرافي وفيديو للأعراس والخطوبة وأعياد الميلاد.',
            'استوديو تصوير متكامل، تغطية كاملة ليوم العرس من التحضير حتى نهاية الحفل مع ألبوم مطبوع.',
        ],
        'makeupArtist' => [
            'خبيرة تجميل متخصصة بمكياج العرائس، خبرة واسعة بمكياج السهرة والمناسبات.',
            'مكياج عرائس وسهرات بلمسة ناعمة تدوم طوال الحفل، مع تسريحة الشعر.',
            'خبيرة تجميل، مكياج عروس كامل مع البشرة والرموش وتسريحة شعر حسب رغبتك.',
            'صالون تجميل متكامل للعرائس، عناية بالبشرة ومكياج وتسريحة ليوم عرسك.',
        ],
        'dj' => [
            'دي جي حفلات وأعراس، أنظمة صوت وإضاءة حديثة وتنسيق كامل لموسيقى الحفل.',
            'إحياء حفلات الزفاف بأحدث أنظمة الصوت والإضاءة، موسيقى عربية وأجنبية حسب الطلب.',
            'دي جي محترف للأعراس والمناسبات، مؤثرات صوتية وإضاءة ليزر وتنسيق كامل للسهرة.',
            'فريق صوتيات متكامل، تغطية حفلات الزفاف والخطوبة مع مقدم حفل.',
        ],
        'weddingHall' => [
            'صالة أفراح واسعة تتسع لأربعمئة شخص، تكييف كامل وموقف سيارات وخدمة ضيافة.',
            'قاعة أفراح فخمة بديكور حديث، إضاءة مميزة وخدمة ضيافة كاملة وتنسيق زهور.',
            'صالة مناسبات مجهزة بالكامل، مسرح وإضاءة ونظام صوت، تتسع لثلاثمئة ضيف.',
            'قصر أفراح بإطلالة مميزة، صالة رجال وصالة نساء منفصلتين مع خدمة كاملة.',
        ],
        'flowers' => [
            'تنسيق باقات الورد الطبيعي للأعراس والمناسبات، توصيل لكل المناطق.',
            'محل زهور متخصص بباقات العرائس وتنسيق قاعات الأفراح بالورد الطبيعي.',
            'ورود طبيعية طازجة يومياً، باقات خطوبة وأعراس وتنسيق سيارة العروس.',
            'تنسيق زهور للمناسبات، باقات هدايا وورود طبيعية ومجففة بتصاميم حديثة.',
        ],
        'gifts' => [
            'هدايا مناسبات وتوزيعات أعراس بتغليف مميز وتصاميم حسب الطلب.',
            'متجر هدايا متنوعة، توزيعات خطوبة وأعراس ومواليد بأسعار مناسبة.',
            'هدايا مميزة لكل المناسبات، تغليف فاخر وإمكانية الطباعة على الهدية.',
            'ركن الهدايا، توزيعات وهدايا تذكارية للأعراس والتخرج وأعياد الميلاد.',
        ],
        'dresses' => [
            'فساتين أعراس وسهرة، تفصيل حسب القياس وإمكانية التأجير.',
            'أتيليه فساتين زفاف بتصاميم حديثة، تفصيل وتأجير مع خدمة التعديل.',
            'بيت أزياء متخصص بفساتين العرائس والسهرة، أقمشة مستوردة وتطريز يدوي.',
            'فساتين خطوبة وزفاف وسهرة، تشكيلة واسعة بمقاسات متنوعة.',
        ],
        'accessories' => [
            'إكسسوارات عرائس، تيجان وأطقم مجوهرات للخطوبة والزفاف.',
            'مجوهرات وإكسسوارات مناسبات، أطقم كاملة للعروس بتصاميم أنيقة.',
            'إكسسوارات نسائية، تيجان عرائس وعقود وأقراط للسهرات والمناسبات.',
            'ركن الإكسسوارات، قطع مميزة تكمل إطلالة العروس يوم عرسها.',
        ],
        'candles' => [
            'شموع مناسبات وديكور، تصاميم خاصة للأعراس وحفلات الخطوبة.',
            'شموع معطرة وشموع ديكور لتزيين قاعات الأفراح والمناسبات.',
            'عالم الشموع، شموع بأشكال وألوان متنوعة للمناسبات والهدايا.',
            'شموع يدوية الصنع بروائح مميزة، مناسبة للديكور والهدايا.',
        ],
        'cakes' => [
            'كيك أعراس وأعياد ميلاد بطبقات وتصاميم حسب الطلب.',
            'معمل حلويات، كيك مناسبات وحلويات شرقية وغربية بمكونات طازجة.',
            'تورتة زفاف بتصاميم فاخرة، وحلويات ضيافة للأعراس والخطوبة.',
            'حلويات ومخبوزات للمناسبات، كيك عيد ميلاد وتورتات خطوبة وزفاف.',
        ],
    ];

    /**
     * Free-text city, exactly as it is in production: inconsistent on purpose.
     * "الشام" / "دمشق" / "Damascus" all refer to the same place, which is why
     * the AI service has to normalize with a city alias map. Do not "clean" this.
     */
    private const CITIES = [
        'دمشق', 'دمشق', 'دمشق', 'الشام', 'الشام', 'Damascus',
        'حلب', 'حلب', 'Aleppo',
        'حمص', 'حمص',
        'اللاذقية', 'اللاذقية', 'Lattakia',
        'طرطوس', 'حماة', 'السويداء', 'درعا',
    ];

    public function definition(): array
    {
        $type = fake()->randomElement(
            array_merge(self::SERVICE_TYPES, self::SELLER_TYPES)
        );

        return array_merge(
            ['phone' => $this->uniquePhone()],
            $this->profileFor($type),
            [
                // Most demo vendors are visible; the seeder overrides these
                // explicitly for the BAIT- rows.
                'is_approved'  => true,
                'is_active'    => true,
                'winding_down' => false,

                'first_name' => fake()->randomElement(['محمد', 'أحمد', 'علي', 'سامر', 'ليلى', 'رندة', 'نور', 'رامي']),
                'last_name'  => fake()->randomElement(['الأحمد', 'الحلبي', 'الشامي', 'خوري', 'العلي', 'حداد']),
                'rating_avg' => fake()->randomFloat(2, 3.4, 5.0),

                // Damascus-ish coordinates, so the `nearest` sort has something
                // sane to work with.
                'latitude'   => fake()->randomFloat(6, 33.40, 33.60),
                'longitude'  => fake()->randomFloat(6, 36.15, 36.40),
            ]
        );
    }

    /** Force a specific vendor_type: Vendor::factory()->type('photographer') */
    public function type(string $vendorType): static
    {
        return $this->state(fn () => array_merge(
            $this->profileFor($vendorType),
            ['phone' => $this->uniquePhone()]
        ));
    }

    /** BAIT: never KYC-approved — must never appear in search results. */
    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved'      => false,
            'rejection_reason' => 'وثائق غير مكتملة',
            'business_name'    => 'BAIT-unapproved ' . $attributes['business_name'],
        ]);
    }

    /** BAIT: banned — must never appear in search results. */
    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active'     => false,
            'winding_down'  => false,
            'business_name' => 'BAIT-banned ' . $attributes['business_name'],
        ]);
    }

    /**
     * Everything that has to stay consistent with vendor_type:
     * booking_style, vendor_style, the business name, and the bio.
     */
    private function profileFor(string $type): array
    {
        $isSeller = in_array($type, self::SELLER_TYPES, true);

        return [
            'vendor_type'   => $type,
            // Mirrors VendorProfileController: seller types are order-based,
            // every other (service) type is appointment-based.
            'booking_style' => $isSeller ? 'order' : 'appointment',
            'vendor_style'  => $isSeller ? 'seller' : 'service_provider',
            'business_name' => fake()->randomElement(self::NAME_PREFIX[$type])
                . ' ' . fake()->randomElement(self::NAME_SUFFIX),
            'bio'           => fake()->randomElement(self::BIOS[$type]),
            'city'          => fake()->randomElement(self::CITIES),
        ];
    }

    /** vendors.phone is unique — keep demo numbers out of any real range. */
    private function uniquePhone(): string
    {
        return '09' . fake()->unique()->numerify('########');
    }
}
