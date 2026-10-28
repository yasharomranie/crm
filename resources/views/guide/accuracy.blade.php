<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>آزمون دقت — راهنمای هوشمند</title>
    @fonts
    <style>
        body { margin: 0; font-family: 'Vazirmatn', ui-sans-serif, system-ui, sans-serif; background: #fff7ed; color: #1c1917; }
        main { max-width: 44rem; margin: 0 auto; padding: 1rem; display: flex; flex-direction: column; gap: 1rem; }
        h1 { font-size: 1.1rem; margin: 0; }
        p { margin: 0; line-height: 2; font-size: .88rem; color: #57534e; }
        .card { background: #fff; border: 1px solid #f1e4d3; border-radius: .8rem; padding: .8rem 1rem; display: flex; flex-direction: column; gap: .6rem; }
        .sample { display: grid; grid-template-columns: 4.5rem 1fr; gap: .8rem; align-items: center; }
        .sample img { width: 4.5rem; height: 4.5rem; object-fit: cover; border-radius: .5rem; }
        .sample .info { display: flex; flex-direction: column; gap: .3rem; font-size: .82rem; }
        select, input[type=file] { font: inherit; max-width: 100%; }
        .ok { color: #15803d; font-weight: 700; }
        .bad { color: #b91c1c; font-weight: 700; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: .6rem; }
        .stat { background: #fff7ed; border-radius: .6rem; padding: .6rem; text-align: center; }
        .stat b { display: block; font-size: 1.2rem; }
        .stat span { font-size: .75rem; color: #57534e; }
        label { font-size: .85rem; display: flex; flex-direction: column; gap: .2rem; }
        input[type=range] { width: 100%; }
        #state { font-weight: 700; }
    </style>
</head>
<body>
<main>
    <h1>آزمون دقت راهنمای هوشمند</h1>
    <p>چند عکس واقعی (از زاویه‌ها و نورهای مختلف، از جمله اشیای دیگر که در فهرست نیستند) انتخاب کنید و برای هر کدام بگویید چه چیزی در عکس است. نتیجه همین‌جا و روی همین گوشی محاسبه می‌شود و جایی ذخیره نمی‌شود. با لغزنده‌ها می‌توانید ببینید حد شباهت چه تأثیری دارد.</p>

    <div class="card">
        <input type="file" id="files" accept="image/*" multiple aria-label="انتخاب عکس‌های آزمون">
        <label for="threshold">حد شباهت: <b id="thresholdValue"></b>
            <input type="range" id="threshold" min="0.3" max="0.99" step="0.005">
        </label>
        <label for="margin">حداقل فاصله با شیء دوم: <b id="marginValue"></b>
            <input type="range" id="margin" min="0" max="0.2" step="0.005">
        </label>
        <span id="state" role="status" aria-live="polite"></span>
    </div>

    <div class="card" id="results" hidden>
        <div class="stats" id="stats"></div>
    </div>

    <div id="samples"></div>
</main>

<script type="module">
    import { loadEngine, embedBlob, rank } from '/guide/guide-engine.js?v=1';

    const $ = (id) => document.getElementById(id);
    const NONE = 'none';
    const samples = [];
    let guide = null;

    const state = (text) => { $('state').textContent = text; };

    function judge(sample) {
        const outcome = rank(sample.vector, guide.objects, Number($('threshold').value), Number($('margin').value));
        sample.outcome = outcome;
        sample.predicted = outcome.matched ? String(outcome.best.object.id) : NONE;
    }

    function verdict(sample) {
        if (sample.expected === undefined) {
            return null;
        }
        if (sample.expected === NONE) {
            return sample.predicted === NONE ? 'correct' : 'falseAccept';
        }

        if (sample.predicted === NONE) {
            return 'falseReject';
        }

        return sample.predicted === sample.expected ? 'correct' : 'wrongObject';
    }

    const LABELS = { correct: 'درست', falseAccept: 'پذیرش اشتباه', falseReject: 'ردِ اشتباه', wrongObject: 'شیء اشتباه' };

    function render() {
        $('thresholdValue').textContent = Number($('threshold').value).toFixed(3);
        $('marginValue').textContent = Number($('margin').value).toFixed(3);

        const counts = { correct: 0, falseAccept: 0, falseReject: 0, wrongObject: 0 };
        let known = 0;
        let unknown = 0;
        const holder = $('samples');
        holder.replaceChildren();

        for (const sample of samples) {
            judge(sample);
            const result = verdict(sample);
            if (result) {
                counts[result]++;
                sample.expected === NONE ? unknown++ : known++;
            }

            const card = document.createElement('div');
            card.className = 'card sample';
            const image = document.createElement('img');
            image.src = sample.preview;
            image.alt = '';
            const info = document.createElement('div');
            info.className = 'info';

            const select = document.createElement('select');
            select.setAttribute('aria-label', 'این عکس چیست؟');
            const empty = new Option('— مشخص کنید —', '');
            select.append(empty, new Option('هیچ‌کدام (شیء ناشناخته)', NONE));
            for (const object of guide.objects) {
                select.append(new Option(object.title, String(object.id)));
            }
            select.value = sample.expected ?? '';
            select.addEventListener('change', () => { sample.expected = select.value === '' ? undefined : select.value; render(); });

            const best = sample.outcome.best;
            const line = document.createElement('span');
            const second = sample.outcome.second;
            line.textContent = best
                ? `بهترین: ${best.object.title} (${best.score.toFixed(3)})${second ? ` · دوم: ${second.object.title} (${second.score.toFixed(3)})` : ''} · پیش‌بینی: ${sample.predicted === NONE ? 'ناشناخته' : best.object.title}`
                : 'تصویر مرجعی برای مقایسه نیست';
            info.append(select, line);

            if (result) {
                const tag = document.createElement('span');
                tag.className = result === 'correct' ? 'ok' : 'bad';
                tag.textContent = LABELS[result];
                info.append(tag);
            }

            card.append(image, info);
            holder.append(card);
        }

        const predictedKnown = counts.correct - countCorrectUnknown() + counts.wrongObject + counts.falseAccept;
        const correctKnown = counts.correct - countCorrectUnknown();
        const percent = (value) => (Number.isFinite(value) ? `${(value * 100).toFixed(0)}٪` : '—');

        $('results').hidden = samples.every((sample) => sample.expected === undefined);
        $('stats').replaceChildren(...[
            ['دقت (Precision)', percent(correctKnown / predictedKnown)],
            ['بازیابی (Recall)', percent(correctKnown / known)],
            ['پذیرش اشتباه اشیای ناشناخته', percent(counts.falseAccept / unknown)],
            ['شیء اشتباه', String(counts.wrongObject)],
        ].map(([label, value]) => {
            const box = document.createElement('div');
            box.className = 'stat';
            const strong = document.createElement('b');
            strong.textContent = value;
            const caption = document.createElement('span');
            caption.textContent = label;
            box.append(strong, caption);

            return box;
        }));
    }

    function countCorrectUnknown() {
        return samples.filter((sample) => sample.expected === NONE && sample.predicted === NONE).length;
    }

    async function init() {
        state('در حال بارگذاری…');
        guide = await (await fetch('/guide/data.json', { headers: { Accept: 'application/json' } })).json();
        $('threshold').value = guide.settings.similarity_threshold;
        $('margin').value = guide.settings.min_margin;
        await loadEngine();
        state(guide.objects.length ? 'آماده؛ عکس‌ها را انتخاب کنید.' : 'هنوز شیئی با تصویر پردازش‌شده نیست.');
        render();
    }

    $('files').addEventListener('change', async (event) => {
        for (const file of event.target.files) {
            state(`در حال پردازش ${file.name}…`);
            samples.push({ vector: await embedBlob(file), preview: URL.createObjectURL(file) });
        }
        event.target.value = '';
        state('آماده.');
        render();
    });
    $('threshold').addEventListener('input', render);
    $('margin').addEventListener('input', render);

    init().catch(() => state('بارگذاری ناموفق بود. اتصال و مدل را بررسی کنید.'));
</script>
</body>
</html>
