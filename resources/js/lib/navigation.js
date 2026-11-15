import { Home, ChefHat, CalendarDays, Users } from 'lucide-vue-next'

export const mainNavigation = [
  { label: 'Pulpit', routeName: 'dashboard', match: 'dashboard', icon: Home },
  { label: 'Przepisy', routeName: 'recipes.index', match: 'recipes.*', icon: ChefHat },
  { label: 'Jadłospisy', routeName: 'meal-plans.index', match: 'meal-plans.*', icon: CalendarDays },
  { label: 'Członkowie rodziny', routeName: 'family-members.index', match: 'family-members.*', icon: Users },
]
