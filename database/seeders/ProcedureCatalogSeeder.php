<?php

namespace Database\Seeders;

use App\Models\Procedure;
use App\Models\ProcedureCategory;
use Illuminate\Database\Seeder;

class ProcedureCatalogSeeder extends Seeder
{
    /**
     * @var array<string, array<int, array{ar: string, en: string}>>
     */
    private array $catalog = [
        'جراحة عامة' => [
            ['ar' => 'استئصال المرارة بالمنظار', 'en' => 'Laparoscopic Cholecystectomy'],
            ['ar' => 'استئصال الزائدة الدودية', 'en' => 'Appendectomy'],
            ['ar' => 'إصلاح الفتق', 'en' => 'Hernia Repair'],
        ],
        'جراحة عظام' => [
            ['ar' => 'تثبيت كسر', 'en' => 'Fracture Fixation'],
            ['ar' => 'استبدال مفصل الركبة', 'en' => 'Knee Replacement'],
        ],
        'نساء وتوليد' => [
            ['ar' => 'عملية قيصرية', 'en' => 'Cesarean Section'],
            ['ar' => 'استئصال الرحم', 'en' => 'Hysterectomy'],
        ],
        'عيون' => [
            ['ar' => 'إزالة المياه البيضاء', 'en' => 'Cataract Surgery'],
        ],
        'أنف وأذن وحنجرة' => [
            ['ar' => 'استئصال اللوزتين', 'en' => 'Tonsillectomy'],
        ],
        'مسالك بولية' => [
            ['ar' => 'تفتيت حصوات الكلى', 'en' => 'Lithotripsy'],
        ],
        'قلب وصدر' => [
            ['ar' => 'قسطرة قلبية', 'en' => 'Cardiac Catheterization'],
        ],
        'أعصاب' => [
            ['ar' => 'استئصال قرص غضروفي', 'en' => 'Discectomy'],
        ],
        'تجميل' => [],
        'أطفال' => [],
        'طوارئ' => [],
    ];

    public function run(): void
    {
        foreach ($this->catalog as $categoryName => $procedures) {
            $category = ProcedureCategory::firstOrCreate(['name' => $categoryName]);

            foreach ($procedures as $procedure) {
                Procedure::updateOrCreate(
                    ['name_ar' => $procedure['ar']],
                    ['category_id' => $category->id, 'name_en' => $procedure['en'], 'is_active' => true],
                );
            }
        }
    }
}
