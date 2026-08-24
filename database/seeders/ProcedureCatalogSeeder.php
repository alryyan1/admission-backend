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
            ['ar' => 'إصلاح فتق السرة', 'en' => 'Umbilical Hernia Repair'],
            ['ar' => 'استئصال الثدي', 'en' => 'Mastectomy'],
            ['ar' => 'استئصال كتلة من الثدي', 'en' => 'Breast Lumpectomy'],
            ['ar' => 'استئصال الغدة الدرقية', 'en' => 'Thyroidectomy'],
            ['ar' => 'استئصال البواسير', 'en' => 'Hemorrhoidectomy'],
            ['ar' => 'استئصال الناسور الشرجي', 'en' => 'Anal Fistulectomy'],
            ['ar' => 'استئصال جزء من القولون', 'en' => 'Colectomy'],
            ['ar' => 'تكميم المعدة', 'en' => 'Sleeve Gastrectomy'],
            ['ar' => 'تحويل مسار المعدة', 'en' => 'Gastric Bypass'],
            ['ar' => 'استئصال الطحال', 'en' => 'Splenectomy'],
            ['ar' => 'خزعة جراحية', 'en' => 'Surgical Biopsy'],
        ],
        'جراحة عظام' => [
            ['ar' => 'تثبيت كسر', 'en' => 'Fracture Fixation'],
            ['ar' => 'استبدال مفصل الركبة', 'en' => 'Knee Replacement'],
            ['ar' => 'استبدال مفصل الورك', 'en' => 'Hip Replacement'],
            ['ar' => 'تنظير الركبة', 'en' => 'Knee Arthroscopy'],
            ['ar' => 'إصلاح الرباط الصليبي', 'en' => 'ACL Reconstruction'],
            ['ar' => 'استئصال الغضروف الهلالي', 'en' => 'Meniscectomy'],
            ['ar' => 'إزالة أدوات التثبيت', 'en' => 'Hardware Removal'],
            ['ar' => 'إصلاح كسر الورك', 'en' => 'Hip Fracture Repair'],
            ['ar' => 'جراحة العمود الفقري (اندماج فقري)', 'en' => 'Spinal Fusion'],
            ['ar' => 'إصلاح كسر الساعد', 'en' => 'Forearm Fracture Repair'],
            ['ar' => 'بتر طرف', 'en' => 'Limb Amputation'],
        ],
        'نساء وتوليد' => [
            ['ar' => 'عملية قيصرية', 'en' => 'Cesarean Section'],
            ['ar' => 'استئصال الرحم', 'en' => 'Hysterectomy'],
            ['ar' => 'استئصال كيس المبيض', 'en' => 'Ovarian Cystectomy'],
            ['ar' => 'ربط قناتي فالوب', 'en' => 'Tubal Ligation'],
            ['ar' => 'توسيع وكحت الرحم', 'en' => 'Dilation and Curettage (D&C)'],
            ['ar' => 'استئصال الورم الليفي الرحمي', 'en' => 'Myomectomy'],
            ['ar' => 'تنظير الرحم', 'en' => 'Hysteroscopy'],
            ['ar' => 'تنظير البطن النسائي', 'en' => 'Gynecologic Laparoscopy'],
        ],
        'عيون' => [
            ['ar' => 'إزالة المياه البيضاء', 'en' => 'Cataract Surgery'],
            ['ar' => 'جراحة الجلوكوما (المياه الزرقاء)', 'en' => 'Glaucoma Surgery'],
            ['ar' => 'استئصال الزجاجية', 'en' => 'Vitrectomy'],
            ['ar' => 'إصلاح انفصال الشبكية', 'en' => 'Retinal Detachment Repair'],
            ['ar' => 'زراعة القرنية', 'en' => 'Corneal Transplant'],
            ['ar' => 'تصحيح الحول', 'en' => 'Strabismus Surgery'],
        ],
        'أنف وأذن وحنجرة' => [
            ['ar' => 'استئصال اللوزتين', 'en' => 'Tonsillectomy'],
            ['ar' => 'استئصال اللحمية', 'en' => 'Adenoidectomy'],
            ['ar' => 'تركيب أنبوب تهوية الأذن', 'en' => 'Ear Tube Insertion (Myringotomy)'],
            ['ar' => 'تقويم الحاجز الأنفي', 'en' => 'Septoplasty'],
            ['ar' => 'جراحة الجيوب الأنفية بالمنظار', 'en' => 'Functional Endoscopic Sinus Surgery (FESS)'],
            ['ar' => 'استئصال الغدة النكفية', 'en' => 'Parotidectomy'],
            ['ar' => 'استئصال الحبال الصوتية (ورم أو عقيدات)', 'en' => 'Vocal Cord Nodule Excision'],
        ],
        'مسالك بولية' => [
            ['ar' => 'تفتيت حصوات الكلى', 'en' => 'Lithotripsy'],
            ['ar' => 'استئصال حصوات الحالب بالمنظار', 'en' => 'Ureteroscopy with Stone Removal'],
            ['ar' => 'استئصال البروستاتا بالمنظار', 'en' => 'Transurethral Resection of the Prostate (TURP)'],
            ['ar' => 'استئصال الكلية', 'en' => 'Nephrectomy'],
            ['ar' => 'استئصال المثانة', 'en' => 'Cystectomy'],
            ['ar' => 'ختان', 'en' => 'Circumcision'],
            ['ar' => 'إصلاح دوالي الخصية', 'en' => 'Varicocelectomy'],
            ['ar' => 'تركيب قسطرة بولية دائمة (JJ Stent)', 'en' => 'Ureteral Stent Placement'],
        ],
        'قلب وصدر' => [
            ['ar' => 'قسطرة قلبية', 'en' => 'Cardiac Catheterization'],
            ['ar' => 'قسطرة قلبية مع دعامة', 'en' => 'Percutaneous Coronary Intervention (Stent)'],
            ['ar' => 'جراحة قلب مفتوح (مجازة الشريان التاجي)', 'en' => 'Coronary Artery Bypass Graft (CABG)'],
            ['ar' => 'تركيب جهاز تنظيم ضربات القلب', 'en' => 'Pacemaker Implantation'],
            ['ar' => 'استبدال أو إصلاح صمام قلبي', 'en' => 'Heart Valve Replacement/Repair'],
            ['ar' => 'تفريغ سائل أو دم من غشاء الجنب', 'en' => 'Chest Tube Thoracostomy'],
            ['ar' => 'استئصال جزء من الرئة', 'en' => 'Lobectomy'],
        ],
        'أعصاب' => [
            ['ar' => 'استئصال قرص غضروفي', 'en' => 'Discectomy'],
            ['ar' => 'استئصال ورم دماغي', 'en' => 'Craniotomy for Tumor Resection'],
            ['ar' => 'تصريف نزيف تحت الجافية', 'en' => 'Evacuation of Subdural Hematoma'],
            ['ar' => 'تركيب تحويلة للسائل الدماغي الشوكي', 'en' => 'Ventriculoperitoneal (VP) Shunt'],
            ['ar' => 'تحرير النفق الرسغي', 'en' => 'Carpal Tunnel Release'],
            ['ar' => 'استئصال صفيحة فقرية', 'en' => 'Laminectomy'],
        ],
        'تجميل' => [
            ['ar' => 'شد البطن', 'en' => 'Abdominoplasty (Tummy Tuck)'],
            ['ar' => 'شفط الدهون', 'en' => 'Liposuction'],
            ['ar' => 'تكبير الثدي', 'en' => 'Breast Augmentation'],
            ['ar' => 'تصغير الثدي', 'en' => 'Breast Reduction'],
            ['ar' => 'تجميل الأنف', 'en' => 'Rhinoplasty'],
            ['ar' => 'شد الوجه', 'en' => 'Facelift'],
            ['ar' => 'ترقيع الجلد', 'en' => 'Skin Grafting'],
            ['ar' => 'إصلاح ندبة أو حرق', 'en' => 'Scar/Burn Reconstruction'],
        ],
        'أطفال' => [
            ['ar' => 'إصلاح فتق إربي عند الأطفال', 'en' => 'Pediatric Inguinal Hernia Repair'],
            ['ar' => 'استئصال الزائدة الدودية عند الأطفال', 'en' => 'Pediatric Appendectomy'],
            ['ar' => 'ختان الأطفال', 'en' => 'Pediatric Circumcision'],
            ['ar' => 'إنزال الخصية الهاجرة', 'en' => 'Orchiopexy (Undescended Testis)'],
            ['ar' => 'إصلاح تضيق البواب', 'en' => 'Pyloromyotomy'],
            ['ar' => 'إصلاح الشفة الأرنبية', 'en' => 'Cleft Lip Repair'],
        ],
        'طوارئ' => [
            ['ar' => 'استئصال الزائدة الدودية الطارئة', 'en' => 'Emergency Appendectomy'],
            ['ar' => 'إصلاح جرح رضحي', 'en' => 'Traumatic Wound Repair'],
            ['ar' => 'استكشاف بطني طارئ', 'en' => 'Emergency Exploratory Laparotomy'],
            ['ar' => 'تصريف خراج', 'en' => 'Abscess Drainage'],
            ['ar' => 'تثبيت كسر طارئ', 'en' => 'Emergency Fracture Fixation'],
            ['ar' => 'استئصال المرارة الطارئ', 'en' => 'Emergency Cholecystectomy'],
        ],
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
