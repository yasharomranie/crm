<x-filament-widgets::widget>
    <x-filament::section heading="امروز چه کار کردی؟" description="یک خط بنویس تا در گزارش روزانهٔ همان پروژه ثبت شود.">
        <form wire:submit="save" class="grid gap-3 md:grid-cols-[14rem_9rem_10rem_1fr_auto] md:items-start">
            <div>
                <x-filament::input.wrapper :valid="! $errors->has('projectId')">
                    <x-filament::input.select wire:model="projectId" aria-label="پروژه">
                        <option value="">پروژه را انتخاب کنید</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                @error('projectId') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
            </div>

            <x-filament::input.wrapper>
                <x-filament::input.select wire:model="kind" aria-label="نوع گزارش">
                    @foreach ($kinds as $kind)
                        <option value="{{ $kind->value }}">{{ $kind->getLabel() }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input.select wire:model="workCategory" aria-label="نوع کار">
                    <option value="">نوع کار (اختیاری)</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}">{{ $category->getLabel() }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <div>
                <x-filament::input.wrapper :valid="! $errors->has('body')">
                    <x-filament::input type="text" wire:model="body" maxlength="5000" placeholder="مثلاً: پست جدید گذاشتم، رمز سایت عوض شد..." aria-label="گزارش امروز" />
                </x-filament::input.wrapper>
                @error('body') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
            </div>

            <x-filament::button type="submit">ثبت</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-widgets::widget>
