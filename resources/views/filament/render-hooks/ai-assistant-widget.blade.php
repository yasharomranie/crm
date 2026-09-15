{{-- Mounted panel-wide via AdminPanelProvider's BODY_END render hook, so
     the floating mascot follows the user across every page - not just the
     dedicated AiAssistant page. The component itself decides whether to
     actually render anything (see AiAssistantWidget::render()). --}}
@livewire('ai-assistant-widget')
