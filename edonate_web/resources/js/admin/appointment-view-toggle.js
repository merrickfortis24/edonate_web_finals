function initializeAppointmentViewToggle() {
  const toggle = document.getElementById('appointmentViewToggle')
  const listPanel = document.getElementById('appointmentListView')
  const calendarPanel = document.getElementById('appointmentCalendarPanel')

  if (!toggle || !listPanel || !calendarPanel) return

  function setView(view, notify = false) {
    const calendarActive = view === 'calendar'
    const activePanel = calendarActive ? calendarPanel : listPanel
    const inactivePanel = calendarActive ? listPanel : calendarPanel

    activePanel.hidden = false
    activePanel.removeAttribute('hidden')
    activePanel.classList.remove('d-none')
    activePanel.style.removeProperty('display')
    activePanel.setAttribute('aria-hidden', 'false')
    inactivePanel.hidden = true
    inactivePanel.setAttribute('hidden', '')
    inactivePanel.classList.add('d-none')
    inactivePanel.setAttribute('aria-hidden', 'true')

    toggle.dataset.currentView = calendarActive ? 'calendar' : 'list'
    toggle.textContent = calendarActive ? 'List View' : 'Calendar View'
    toggle.setAttribute('aria-pressed', calendarActive ? 'true' : 'false')
    toggle.setAttribute(
      'aria-label',
      calendarActive
        ? 'Calendar view is active. Switch to List View'
        : 'List view is active. Switch to Calendar View',
    )
    toggle.classList.toggle('appointment-view-btn--active', calendarActive)
    toggle.classList.toggle('appointment-view-btn--outline', !calendarActive)

    if (notify) {
      document.dispatchEvent(new CustomEvent('edonate:appointment-view-change', {
        detail: { view: calendarActive ? 'calendar' : 'list' },
      }))
    }
  }

  toggle.addEventListener('click', () => {
    setView(toggle.dataset.currentView === 'calendar' ? 'list' : 'calendar', true)
  })

  setView(toggle.dataset.currentView === 'calendar' ? 'calendar' : 'list')
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeAppointmentViewToggle, { once: true })
} else {
  initializeAppointmentViewToggle()
}
