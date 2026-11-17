<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { AlertCircle, CalendarDays, CheckCircle2, Info, Loader2, Plus, SearchX, TriangleAlert } from 'lucide-vue-next'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/Components/ui/alert-dialog'
import { Avatar, AvatarFallback } from '@/Components/ui/avatar'
import AppBrand from '@/Components/Common/AppBrand.vue'
import MainNav from '@/Components/Common/MainNav.vue'
import UserMenu from '@/Components/Common/UserMenu.vue'
import { mainNavigation } from '@/lib/navigation'
import TextLink from '@/Components/Common/TextLink.vue'
import PageHeader from '@/Components/Common/PageHeader.vue'
import EmptyState from '@/Components/Common/EmptyState.vue'
import { pluralize } from '@/lib/format'
import ConfirmDialog from '@/Components/Common/ConfirmDialog.vue'
import PdfDownloadMenu from '@/Components/MealPlans/PdfDownloadMenu.vue'
import MealPlanGrid from '@/Components/MealPlans/MealPlanGrid.vue'
import RecipeSwapDialog from '@/Components/MealPlans/RecipeSwapDialog.vue'
import MealPlanTile from '@/Components/MealPlans/MealPlanTile.vue'
import SortMenu from '@/Components/MealPlans/SortMenu.vue'
import StatusFilterPills from '@/Components/MealPlans/StatusFilterPills.vue'
import ShoppingList from '@/Components/MealPlans/ShoppingList.vue'
import FormField from '@/Components/Common/FormField.vue'
import ServerPagination from '@/Components/Common/ServerPagination.vue'
import SortableHeader from '@/Components/Common/SortableHeader.vue'
import CategoryBadge from '@/Components/recipes/CategoryBadge.vue'
import StatusTag from '@/Components/MealPlans/StatusTag.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Toggle } from '@/Components/ui/toggle'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card'
import { Checkbox } from '@/Components/ui/checkbox'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/Components/ui/collapsible'
import {
  Combobox,
  ComboboxAnchor,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from '@/Components/ui/combobox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/Components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from '@/Components/ui/pagination'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select'
import { Separator } from '@/Components/ui/separator'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/Components/ui/sheet'
import { Skeleton } from '@/Components/ui/skeleton'
import { Toaster } from '@/Components/ui/sonner'
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { Textarea } from '@/Components/ui/textarea'

const colorSwatches = [
  { token: 'background', classes: 'bg-background text-foreground' },
  { token: 'foreground', classes: 'bg-foreground text-background' },
  { token: 'card', classes: 'bg-card text-card-foreground' },
  { token: 'muted', classes: 'bg-muted text-foreground' },
  { token: 'muted-foreground', classes: 'bg-muted-foreground text-background' },
  { token: 'secondary', classes: 'bg-secondary text-secondary-foreground' },
  { token: 'primary', classes: 'bg-primary text-primary-foreground' },
  { token: 'accent', classes: 'bg-accent text-accent-foreground' },
  { token: 'destructive', classes: 'bg-destructive text-destructive-foreground' },
  { token: 'border', classes: 'bg-border text-foreground' },
  { token: 'input', classes: 'bg-input text-foreground' },
  { token: 'ring', classes: 'bg-ring text-primary-foreground' },
  { token: 'success', classes: 'bg-success text-success-foreground' },
  { token: 'warning', classes: 'bg-warning text-warning-foreground' },
  { token: 'info', classes: 'bg-info text-info-foreground' },
  { token: 'meal-breakfast', classes: 'bg-meal-breakfast text-meal-breakfast-foreground' },
  { token: 'meal-supper', classes: 'bg-meal-supper text-meal-supper-foreground' },
  { token: 'meal-dinner', classes: 'bg-meal-dinner text-meal-dinner-foreground' },
  { token: 'sidebar', classes: 'bg-sidebar text-sidebar-foreground' },
  { token: 'sidebar-active', classes: 'bg-sidebar-active text-sidebar-active-foreground' },
  { token: 'highlight', classes: 'bg-highlight text-highlight-foreground' },
  { token: 'overlay', classes: 'bg-overlay text-foreground' },
  { token: 'sidebar-foreground', classes: 'bg-sidebar-foreground text-background' },
]

const typographyRoles = [
  { role: 'Display', classes: 'text-display', sample: 'Cały tydzień na jednej kartce' },
  { role: 'Hasło gościa', classes: 'text-hero', sample: 'Cały tydzień jedzenia na jednej kartce.' },
  { role: 'Tytuł strony', classes: 'text-page-title', sample: 'Pulpit' },
  { role: 'Liczba w kafelku', classes: 'text-stat tabular-nums', sample: '21' },
  { role: 'Tytuł sekcji', classes: 'text-section-title', sample: 'Ten tydzień' },
  { role: 'Tytuł dialogu', classes: 'text-dialog-title', sample: 'Usuń przepis' },
  { role: 'Tekst', classes: 'text-body', sample: 'Dodaj przepisy, a Menu Maker ułoży tydzień.' },
  { role: 'Etykieta', classes: 'text-label', sample: 'Dodaj przepis' },
  { role: 'Metadane', classes: 'text-meta text-muted-foreground', sample: '3 porcje · 12 lis 2026' },
]

const amounts = [
  { name: 'Mąka pszenna', amount: '1,25 kg' },
  { name: 'Mleko 2%', amount: '0,50 l' },
  { name: 'Masło', amount: '250,00 g' },
]

const shapes = [
  { label: 'rounded-sm (8 px)', classes: 'rounded-sm' },
  { label: 'rounded-md (12 px)', classes: 'rounded-md' },
  { label: 'rounded-lg (20 px)', classes: 'rounded-lg' },
  { label: 'rounded-xl (24 px)', classes: 'rounded-xl' },
  { label: 'rounded-full', classes: 'rounded-full' },
]

const buttonVariants = ['default', 'destructive', 'outline', 'secondary', 'ghost', 'link', 'dashed']
const chipTones = [
  { variant: 'default', label: 'Wszystkie', count: 24 },
  { variant: 'breakfast', label: 'Śniadanie', count: 8 },
  { variant: 'supper', label: 'Obiad', count: 6 },
  { variant: 'dinner', label: 'Kolacja', count: 10 },
]
const buttonSizes = [
  { size: 'xs', label: 'Mały xs' },
  { size: 'sm', label: 'Mały' },
  { size: 'default', label: 'Domyślny' },
  { size: 'lg', label: 'Duży' },
]

const badgeVariants = [
  { variant: 'default', label: 'Domyślna' },
  { variant: 'secondary', label: 'Drugorzędna' },
  { variant: 'destructive', label: 'Błąd' },
  { variant: 'outline', label: 'Kontur' },
  { variant: 'breakfast', label: 'Śniadanie' },
  { variant: 'supper', label: 'Obiad' },
  { variant: 'dinner', label: 'Kolacja' },
  { variant: 'success', label: 'Gotowy' },
  { variant: 'warning', label: 'Ostrzeżenie' },
  { variant: 'info', label: 'W toku' },
  { variant: 'muted', label: 'Wyciszona' },
  { variant: 'tag', label: 'Znacznik' },
]

const selectOptions = [
  { value: 'breakfast', label: 'Śniadanie' },
  { value: 'supper', label: 'Obiad' },
  { value: 'dinner', label: 'Kolacja' },
]

const recipes = [
  { name: 'Owsianka z jabłkiem', kcal: 320, portions: 2 },
  { name: 'Rosół z makaronem', kcal: 410, portions: 4 },
  { name: 'Kanapki z pastą jajeczną', kcal: 380, portions: 3 },
]

const comboboxValue = ref(undefined)
const page = ref(2)

const shellItems = mainNavigation.map((item) => ({
  label: item.label,
  href: route(item.routeName),
  icon: item.icon,
  current: item.routeName === 'recipes.index',
}))
const shellUser = { name: 'Anna Kowalska', email: 'anna@example.com' }
const appCategories = ['breakfast', 'supper', 'dinner']
const appStatuses = ['draft', 'pending', 'processing', 'done', 'error']
const appSort = ref({ column: 'name', direction: 'asc' })
const appPaginationMeta = ref({
  current_page: 2,
  last_page: 5,
  per_page: 10,
  total: 50,
  from: 11,
  to: 20,
})
const planStartDate = '2026-06-15'
const planRecipeNames = [
  'Owsianka z jabłkiem', 'Rosół z makaronem', 'Kasza z warzywami',
  'Jajecznica ze szczypiorkiem', 'Pomidorowa z ryżem', 'Pierogi ruskie',
  'Kanapki z pastą jajeczną', 'Gulasz wieprzowy', 'Sałatka grecka',
  'Naleśniki z serem', 'Zupa krem z dyni', 'Makaron z pesto',
  'Płatki z mlekiem', 'Kotlet z ziemniakami', 'Leczo z kiełbasą',
  'Tosty z awokado', 'Barszcz czerwony', 'Ryba z pieca',
  'Omlet z warzywami', 'Chili sin carne', 'Tortilla z kurczakiem',
]
const planMeals = planRecipeNames
  .map((name, index) => {
    const day = Math.floor(index / 3)
    const base = {
      id: index + 1,
      meal_date: `2026-06-${String(15 + day).padStart(2, '0')}`,
      meal_category: appCategories[index % 3],
    }

    if (index + 1 === 8) {
      return { ...base, ingredients: [] }
    }

    // Thursday's Obiad (id 11) feeds Friday's Obiad (id 14), and Friday's Kolacja (id 15) feeds Saturday's Śniadanie (id 16); each carried-over meal shows the cooked dish.
    const links = {
      11: { feeds_meal_id: 14 },
      14: { from_meal_id: 11, dish: 10 },
      15: { feeds_meal_id: 16 },
      16: { from_meal_id: 15, dish: 14 },
    }
    const { dish = index, ...link } = links[index + 1] ?? {}

    return {
      ...base,
      ...link,
      recipe: { id: dish + 1, name: planRecipeNames[dish], calories: 1200 + dish * 40, servings: 4, deleted: dish === 4 },
      ingredients: index === 0
        ? [
            { ingredient_id: 1, name: 'Płatki owsiane', display: '200 g' },
            { ingredient_id: 2, name: 'Jabłko', display: '2 szt.' },
            { ingredient_id: 3, name: 'Mleko', display: '0,5 l' },
          ]
        : [],
    }
  })
const planShoppingList = {
  items: [
    { ingredient_id: 1, name: 'Jajka', quantity: '42', unit: 'szt.', display: '42 szt.' },
    { ingredient_id: 2, name: 'Mąka', quantity: '4200', unit: 'g', display: '4,2 kg' },
    { ingredient_id: 3, name: 'Mleko', quantity: '2.1', unit: 'l', display: '2,1 l' },
  ],
  recipes_without_ingredients: ['Sałatka grecka'],
}
const emptyShoppingList = { items: [], recipes_without_ingredients: [] }
const swapOpen = ref(false)
const swapMeal = ref(planMeals[0])
const openSwap = (meal) => {
  swapMeal.value = meal
  swapOpen.value = true
}
const swapOptions = [
  { id: 1, name: 'Owsianka z jabłkiem', calories: '1200' },
  { id: 4, name: 'Jajecznica ze szczypiorkiem', calories: '1320' },
  { id: 13, name: 'Płatki z mlekiem', calories: '980' },
  { id: 16, name: 'Tosty z awokado', calories: null },
]
const demoPlanLinks = (id, withPdf) => ({
  self: `#ds-plan-${id}`,
  pdf: withPdf ? `#ds-plan-${id}-pdf` : null,
  pdf_with_recipes: withPdf ? `#ds-plan-${id}-pdf-recipes` : null,
})
const demoPlanTiles = [
  { id: 1, status: 'draft', start_date: '2026-06-29', end_date: '2026-07-05', created_at: '2026-06-22T09:30:00', meals_count: 20, withPdf: false },
  { id: 2, status: 'processing', start_date: '2026-06-22', end_date: '2026-06-28', created_at: '2026-06-15T08:15:00', meals_count: 21, withPdf: false },
  { id: 3, status: 'done', start_date: '2026-06-15', end_date: '2026-06-21', created_at: '2026-06-08T18:00:00', meals_count: 21, withPdf: true },
  { id: 4, status: 'error', start_date: '2026-06-08', end_date: '2026-06-14', created_at: '2026-06-01T12:45:00', meals_count: 0, withPdf: false },
  { id: 5, status: 'pending', start_date: '2026-06-01', end_date: '2026-06-07', created_at: '2026-05-25T07:20:00', meals_count: 9, withPdf: false },
].map(({ withPdf, ...plan }) => ({
  ...plan,
  updated_at: plan.created_at,
  logs_count: 0,
  links: demoPlanLinks(plan.id, withPdf),
}))
const demoPlanStatuses = [
  { value: 'draft', label: 'Szkic' },
  { value: 'pending', label: 'Oczekuje' },
  { value: 'processing', label: 'Generowanie' },
  { value: 'done', label: 'Gotowy' },
  { value: 'error', label: 'Błąd' },
]
const demoPlanStatus = ref('pending')
const demoPlanSort = ref({ column: 'created_at', direction: 'desc' })
const demoPlanCount = 22
const demoPlanForms = { one: 'jadłospis', few: 'jadłospisy', many: 'jadłospisów' }
const confirmOpen = ref(false)
const processingOpen = ref(false)
const fieldOpen = ref(false)

const handleAppSort = (value) => {
  appSort.value = value
}

const handleAppPageChange = (newPage) => {
  appPaginationMeta.value = {
    ...appPaginationMeta.value,
    current_page: newPage,
    from: (newPage - 1) * 10 + 1,
    to: newPage * 10,
  }
}

const showToast = () => {
  toast('Zapisano przepis', {
    description: 'Zmiany są już widoczne w jadłospisie.',
    duration: Infinity,
  })
}
</script>

<template>
  <Head title="Design system" />

  <div class="min-h-screen bg-background text-foreground">
    <Toaster />

    <main class="mx-auto flex max-w-6xl flex-col gap-10 px-4 py-10">
      <header class="flex flex-col gap-2">
        <h1 class="text-2xl font-semibold tracking-tight">Design system</h1>
        <p class="text-sm text-muted-foreground">
          Tokeny, typografia i prymitywy interfejsu w stanach referencyjnych.
        </p>
      </header>

      <section aria-labelledby="ds-kolory" class="flex flex-col gap-4">
        <h2 id="ds-kolory" class="text-lg font-semibold">Kolory</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
          <div
            v-for="swatch in colorSwatches"
            :key="swatch.token"
            :class="['flex h-20 items-end rounded-lg border p-2 text-xs font-medium', swatch.classes]"
          >
            {{ swatch.token }}
          </div>
        </div>
      </section>

      <section aria-labelledby="ds-typografia" class="flex flex-col gap-4">
        <h2 id="ds-typografia" class="text-lg font-semibold">Typografia</h2>
        <div class="flex flex-col gap-3">
          <div v-for="item in typographyRoles" :key="item.role" class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">{{ item.role }}</span>
            <span :class="item.classes">{{ item.sample }}</span>
          </div>
        </div>
        <ul class="flex max-w-sm flex-col rounded-lg border bg-card text-sm">
          <li
            v-for="item in amounts"
            :key="item.name"
            class="flex items-center justify-between gap-4 border-b px-4 py-2 last:border-b-0"
          >
            <span>{{ item.name }}</span>
            <span class="text-right tabular-nums">{{ item.amount }}</span>
          </li>
        </ul>
      </section>

      <section aria-labelledby="ds-ksztalt" class="flex flex-col gap-4">
        <h2 id="ds-ksztalt" class="text-lg font-semibold">Kształt i cienie</h2>
        <div class="flex flex-wrap items-center gap-4">
          <div
            v-for="shape in shapes"
            :key="shape.label"
            :class="['flex size-20 items-center justify-center border bg-muted p-1 text-center text-xs', shape.classes]"
          >
            {{ shape.label }}
          </div>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div class="rounded-lg border-2 border-foreground bg-card p-4 text-sm shadow-sticker">
            Kafel: kontur 2px, shadow-sticker
          </div>
          <div class="rounded-xl border-2 border-foreground bg-card p-4 text-sm shadow-dialog">
            Dialog: shadow-dialog
          </div>
          <div class="rounded-lg bg-card p-4 text-sm shadow-sm">shadow-sm</div>
        </div>
      </section>

      <section aria-labelledby="ds-przyciski" class="flex flex-col gap-4">
        <h2 id="ds-przyciski" class="text-lg font-semibold">Przyciski</h2>
        <div class="flex flex-wrap items-center gap-2">
          <Button v-for="variant in buttonVariants" :key="variant" :variant="variant">
            {{ variant }}
          </Button>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Button v-for="variant in buttonVariants" :key="variant" :variant="variant" disabled>
            {{ variant }}
          </Button>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Button v-for="item in buttonSizes" :key="item.size" :size="item.size">
            {{ item.label }}
          </Button>
          <Button size="icon" aria-label="Dodaj przepis">
            <Plus />
          </Button>
          <Button disabled>
            <Loader2 class="animate-spin" />
            Zapisywanie…
          </Button>
        </div>
        <h3 class="text-base font-semibold">Pigułki filtra</h3>
        <div class="flex flex-wrap items-center gap-2">
          <Toggle v-for="chip in chipTones" :key="chip.variant" :variant="chip.variant">
            {{ chip.label }}
            <span>{{ chip.count }}</span>
          </Toggle>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Toggle :model-value="true">
            Wszystkie
            <span>24</span>
          </Toggle>
          <Toggle variant="breakfast" :model-value="true">
            Śniadanie
            <span>8</span>
          </Toggle>
          <Toggle disabled>
            Niedostępne
            <span>0</span>
          </Toggle>
          <Toggle size="sm">
            Mała pigułka
            <span>3</span>
          </Toggle>
        </div>
      </section>

      <section aria-labelledby="ds-odznaki" class="flex flex-col gap-4">
        <h2 id="ds-odznaki" class="text-lg font-semibold">Odznaki</h2>
        <div class="flex flex-wrap items-center gap-2">
          <Badge v-for="item in badgeVariants" :key="item.variant" :variant="item.variant">
            {{ item.label }}
          </Badge>
        </div>
        <div class="flex flex-wrap items-center gap-2" aria-label="Znacznik powiązanego posiłku (wariant tag na wypełnieniu posiłku)">
          <span
            v-for="swatch in colorSwatches.filter((item) => item.token.startsWith('meal-'))"
            :key="swatch.token"
            :class="['inline-flex items-center rounded-md p-2.5', swatch.classes]"
          >
            <Badge variant="tag">Na 2 dni</Badge>
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-2" aria-label="Status">
          <Badge variant="draft" size="status">Szkic</Badge>
          <Badge variant="muted" size="status">Oczekuje</Badge>
          <Badge variant="info" size="status">
            <Loader2 class="size-3 animate-spin motion-reduce:animate-none" aria-hidden="true" />
            Generowanie
          </Badge>
          <Badge variant="success" size="status">Gotowy</Badge>
          <Badge variant="destructive" size="status">Błąd</Badge>
        </div>
      </section>

      <section aria-labelledby="ds-komunikaty" class="flex flex-col gap-4">
        <h2 id="ds-komunikaty" class="text-lg font-semibold">Komunikaty</h2>
        <div class="flex max-w-2xl flex-col gap-3">
          <Alert>
            <Info />
            <AlertTitle>Wskazówka</AlertTitle>
            <AlertDescription>Jadłospis generuje się w tle, możesz zamknąć tę stronę.</AlertDescription>
          </Alert>
          <Alert variant="destructive">
            <AlertCircle />
            <AlertTitle>Nie udało się zapisać</AlertTitle>
            <AlertDescription>Sprawdź połączenie i spróbuj ponownie.</AlertDescription>
          </Alert>
          <Alert variant="warning">
            <TriangleAlert />
            <AlertTitle>Ilości bez dopracowania</AlertTitle>
            <AlertDescription>AI nie odpowiedziało, użyliśmy ilości wyliczonych automatycznie.</AlertDescription>
          </Alert>
          <Alert variant="info">
            <Info />
            <AlertTitle>Generowanie w toku</AlertTitle>
            <AlertDescription>Przygotowujemy jadłospis na cały tydzień.</AlertDescription>
          </Alert>
          <Alert variant="success">
            <CheckCircle2 />
            <AlertTitle>Jadłospis gotowy</AlertTitle>
            <AlertDescription>Możesz pobrać PDF z listą zakupów.</AlertDescription>
          </Alert>
        </div>
      </section>

      <section aria-labelledby="ds-formularze" class="flex flex-col gap-4">
        <h2 id="ds-formularze" class="text-lg font-semibold">Formularze</h2>
        <div class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="flex flex-col gap-2">
            <Label for="ds-input-default">Nazwa przepisu</Label>
            <Input id="ds-input-default" model-value="Zupa pomidorowa" />
          </div>
          <div class="flex flex-col gap-2">
            <Label for="ds-input-placeholder">Opis</Label>
            <Input id="ds-input-placeholder" placeholder="Krótki opis przepisu" />
          </div>
          <div class="flex flex-col gap-2">
            <Label for="ds-input-disabled">Autor</Label>
            <Input id="ds-input-disabled" model-value="Jan Kowalski" disabled />
          </div>
          <FormField id="ds-field-default" label="Nazwa przepisu">
            <template #default="{ id }">
              <Input :id="id" model-value="Zupa pomidorowa" />
            </template>
          </FormField>
          <FormField
            id="ds-field-description"
            label="Liczba porcji"
            description="Dla ilu osób przygotowujesz przepis."
          >
            <template #default="{ id, invalid, describedBy }">
              <Input
                :id="id"
                model-value="4"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
              />
            </template>
          </FormField>
          <FormField id="ds-field-error" label="Liczba porcji" error="Podaj liczbę porcji.">
            <template #default="{ id, invalid, describedBy }">
              <Input
                :id="id"
                model-value="abc"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
              />
            </template>
          </FormField>
          <FormField id="ds-field-password" label="Hasło">
            <template #default="{ id, invalid, describedBy }">
              <Input
                :id="id"
                type="password"
                model-value="sekretne-haslo"
                autocomplete="new-password"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
              />
            </template>
          </FormField>
          <div class="flex flex-col gap-2">
            <Label for="ds-textarea-default">Instrukcja</Label>
            <Textarea id="ds-textarea-default" placeholder="Opisz kolejne kroki" />
          </div>
          <div class="flex flex-col gap-2">
            <Label for="ds-textarea-disabled">Notatki</Label>
            <Textarea id="ds-textarea-disabled" model-value="Pole zablokowane" disabled />
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-6">
          <div class="flex items-center gap-2">
            <Checkbox id="ds-checkbox-off" />
            <Label for="ds-checkbox-off">Bez glutenu</Label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox id="ds-checkbox-on" :model-value="true" />
            <Label for="ds-checkbox-on">Wegetariańskie</Label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox id="ds-checkbox-disabled" disabled />
            <Label for="ds-checkbox-disabled">Niedostępne</Label>
          </div>
        </div>
        <div class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="flex flex-col gap-2">
            <Label>Kategoria (Select)</Label>
            <Select>
              <SelectTrigger aria-label="Kategoria">
                <SelectValue placeholder="Wybierz kategorię" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in selectOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="flex flex-col gap-2">
            <Label>Kategoria (Combobox)</Label>
            <Combobox v-model="comboboxValue">
              <ComboboxAnchor class="w-full">
                <ComboboxInput
                  aria-label="Kategoria"
                  placeholder="Szukaj kategorii"
                  :display-value="(value) => value?.label ?? ''"
                />
              </ComboboxAnchor>
              <ComboboxList>
                <ComboboxEmpty class="p-2 text-sm text-muted-foreground">Brak wyników</ComboboxEmpty>
                <ComboboxItem v-for="option in selectOptions" :key="option.value" :value="option">
                  {{ option.label }}
                </ComboboxItem>
              </ComboboxList>
            </Combobox>
          </div>
        </div>
      </section>

      <section aria-labelledby="ds-karta-tabela" class="flex flex-col gap-4">
        <h2 id="ds-karta-tabela" class="text-lg font-semibold">Karta i tabela</h2>
        <Card class="max-w-md">
          <CardHeader>
            <CardTitle>Zupa pomidorowa</CardTitle>
            <CardDescription>Klasyczny przepis na obiad dla całej rodziny.</CardDescription>
          </CardHeader>
          <CardContent class="text-sm">Czas przygotowania: 40 minut, 4 porcje.</CardContent>
          <CardFooter class="gap-2">
            <Button size="sm">Otwórz</Button>
            <Button size="sm" variant="outline">Edytuj</Button>
          </CardFooter>
        </Card>
        <div class="rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Przepis</TableHead>
                <TableHead class="text-right">Kalorie</TableHead>
                <TableHead class="text-right">Porcje</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="recipe in recipes" :key="recipe.name">
                <TableCell>{{ recipe.name }}</TableCell>
                <TableCell class="text-right tabular-nums">{{ recipe.kcal }}</TableCell>
                <TableCell class="text-right tabular-nums">{{ recipe.portions }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
        <div class="rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Przepis</TableHead>
                <TableHead class="text-right">Kalorie</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableEmpty :colspan="2">Brak przepisów</TableEmpty>
            </TableBody>
          </Table>
        </div>
      </section>

      <section aria-labelledby="ds-ladowanie" class="flex flex-col gap-4">
        <h2 id="ds-ladowanie" class="text-lg font-semibold">Ładowanie i rozwijanie</h2>
        <div class="flex max-w-md flex-col gap-2">
          <Skeleton class="h-4 w-full" />
          <Skeleton class="h-4 w-3/4" />
          <Skeleton class="h-4 w-1/2" />
        </div>
        <div class="flex max-w-md flex-col gap-3">
          <Collapsible class="rounded-lg border bg-card p-3">
            <CollapsibleTrigger as-child>
              <Button variant="ghost" size="sm">Składniki (zwinięte)</Button>
            </CollapsibleTrigger>
            <CollapsibleContent class="pt-2 text-sm">Pomidory, bulion, makaron.</CollapsibleContent>
          </Collapsible>
          <Collapsible default-open class="rounded-lg border bg-card p-3">
            <CollapsibleTrigger as-child>
              <Button variant="ghost" size="sm">Składniki (rozwinięte)</Button>
            </CollapsibleTrigger>
            <CollapsibleContent class="pt-2 text-sm">Mąka, jajka, mleko, masło.</CollapsibleContent>
          </Collapsible>
        </div>
        <Separator />
        <div class="flex items-center gap-3">
          <Avatar>
            <AvatarFallback>JK</AvatarFallback>
          </Avatar>
          <span class="text-sm">Jan Kowalski</span>
          <Avatar variant="sticker">
            <AvatarFallback>JK</AvatarFallback>
          </Avatar>
          <span class="text-sm">Naklejka (pasek górny)</span>
        </div>
      </section>

      <section aria-labelledby="ds-paginacja" class="flex flex-col gap-4">
        <h2 id="ds-paginacja" class="text-lg font-semibold">Paginacja</h2>
        <Pagination
          v-slot="{ page: currentPage }"
          v-model:page="page"
          :items-per-page="10"
          :total="50"
          :sibling-count="1"
          show-edges
          aria-label="Paginacja"
        >
          <PaginationContent v-slot="{ items }">
            <PaginationPrevious>
              <span>Poprzednia</span>
            </PaginationPrevious>
            <template v-for="(item, index) in items" :key="index">
              <PaginationItem v-if="item.type === 'page'" :value="item.value" :is-active="item.value === currentPage">
                {{ item.value }}
              </PaginationItem>
              <PaginationEllipsis v-else :index="index" />
            </template>
            <PaginationNext>
              <span>Następna</span>
            </PaginationNext>
          </PaginationContent>
        </Pagination>
      </section>

      <section aria-labelledby="ds-nakladki" class="flex flex-col gap-4">
        <h2 id="ds-nakladki" class="text-lg font-semibold">Nakładki</h2>
        <p class="text-sm text-muted-foreground">
          Select i Combobox z sekcji Formularze to pozostałe dwie nakładki.
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <Dialog>
            <DialogTrigger as-child>
              <Button variant="outline">Otwórz dialog</Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Nowy przepis</DialogTitle>
                <DialogDescription>Podaj nazwę, aby dodać przepis do biblioteki.</DialogDescription>
              </DialogHeader>
              <div class="flex flex-col gap-2">
                <Label for="ds-dialog-name">Nazwa</Label>
                <Input id="ds-dialog-name" placeholder="Np. Zupa pomidorowa" />
              </div>
              <DialogFooter>
                <Button>Zapisz</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          <AlertDialog>
            <AlertDialogTrigger as-child>
              <Button variant="destructive">Otwórz potwierdzenie</Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Usunąć przepis?</AlertDialogTitle>
                <AlertDialogDescription>Tej operacji nie można cofnąć.</AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Anuluj</AlertDialogCancel>
                <AlertDialogAction>Usuń</AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>

          <Sheet>
            <SheetTrigger as-child>
              <Button variant="outline">Otwórz panel</Button>
            </SheetTrigger>
            <SheetContent>
              <SheetHeader>
                <SheetTitle>Filtry</SheetTitle>
                <SheetDescription>Zawęź listę przepisów według kategorii.</SheetDescription>
              </SheetHeader>
            </SheetContent>
          </Sheet>

          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="outline">Otwórz menu</Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
              <DropdownMenuLabel>Konto</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem>Profil</DropdownMenuItem>
              <DropdownMenuItem>Wyloguj</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem variant="destructive">Usuń</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <Button variant="outline" @click="showToast">Pokaż powiadomienie</Button>
        </div>
      </section>

      <section aria-labelledby="ds-powloka" class="flex flex-col gap-4">
        <h2 id="ds-powloka" class="text-lg font-semibold">Powłoka</h2>
        <div data-testid="shell-top-bar" class="flex items-center gap-6 bg-sidebar px-8 py-4">
          <AppBrand href="#" />
          <MainNav :items="shellItems" orientation="horizontal" class="mx-auto" />
          <UserMenu :user="shellUser" />
        </div>
        <div data-testid="shell-drawer-nav" class="w-72 rounded-lg border-2 border-foreground bg-background p-5">
          <MainNav :items="shellItems" orientation="vertical" />
        </div>
        <div class="flex flex-wrap items-center gap-6">
          <div data-testid="shell-brand-inverse" class="rounded-lg bg-highlight p-6 text-highlight-foreground">
            <AppBrand href="#" tone="inverse" />
          </div>
          <p class="text-sm">
            <span class="text-muted-foreground">Masz już konto?</span>
            <TextLink href="#">Zaloguj się</TextLink>
          </p>
        </div>
      </section>

      <section aria-labelledby="ds-komponenty-aplikacji" class="flex flex-col gap-4">
        <h2 id="ds-komponenty-aplikacji" class="text-lg font-semibold">Komponenty aplikacji</h2>

        <div class="flex flex-wrap items-center gap-2">
          <CategoryBadge v-for="category in appCategories" :key="category" :category="category" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <StatusTag v-for="status in appStatuses" :key="status" :value="status" />
        </div>

        <Table>
          <TableHeader>
            <TableRow>
              <SortableHeader
                column="name"
                :sort="appSort.column"
                :direction="appSort.direction"
                @sort-change="handleAppSort"
              >
                Nazwa
              </SortableHeader>
              <SortableHeader
                column="kcal"
                :sort="appSort.column"
                :direction="appSort.direction"
                @sort-change="handleAppSort"
              >
                Kalorie
              </SortableHeader>
              <SortableHeader
                column="portions"
                :sort="appSort.column"
                :direction="appSort.direction"
                @sort-change="handleAppSort"
              >
                Porcje
              </SortableHeader>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="recipe in recipes" :key="recipe.name">
              <TableCell>{{ recipe.name }}</TableCell>
              <TableCell class="tabular-nums">{{ recipe.kcal }}</TableCell>
              <TableCell class="tabular-nums">{{ recipe.portions }}</TableCell>
            </TableRow>
          </TableBody>
        </Table>

        <ServerPagination :meta="appPaginationMeta" @page-change="handleAppPageChange" />

        <div class="flex flex-wrap items-center gap-2">
          <Button variant="destructive" @click="confirmOpen = true">Otwórz ConfirmDialog</Button>
          <Button variant="outline" @click="processingOpen = true">Pokaż dialog w toku</Button>
          <Button variant="destructive" @click="fieldOpen = true">Potwierdź z polem</Button>
        </div>

        <div class="flex flex-col gap-6 rounded-lg border bg-card p-6">
          <PageHeader
            as="h2"
            title="Jadłospisy"
            :description="`Przeglądaj wygenerowane jadłospisy · ${demoPlanCount} ${pluralize(demoPlanCount, demoPlanForms)}`"
          >
            <template #actions>
              <Button><Plus />Wygeneruj jadłospis</Button>
            </template>
          </PageHeader>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
          <StatusFilterPills v-model="demoPlanStatus" :statuses="demoPlanStatuses" />
          <SortMenu
            :sort="demoPlanSort.column"
            :direction="demoPlanSort.direction"
            @sort-change="demoPlanSort = $event"
          />
        </div>

        <ul class="flex flex-col gap-3.5">
          <li v-for="plan in demoPlanTiles" :key="plan.id">
            <MealPlanTile :item="plan" demo />
          </li>
        </ul>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Card>
            <EmptyState
              class="py-12"
              :icon="CalendarDays"
              title="Nie masz jeszcze jadłospisów"
              description="Wygeneruj tydzień 7 dni × 3 posiłki z własnych przepisów."
            >
              <Button>Wygeneruj pierwszy jadłospis</Button>
            </EmptyState>
          </Card>
          <Card>
            <EmptyState class="py-12" :icon="CalendarDays" title="Brak jadłospisów o wybranym statusie">
              <Button variant="outline">Wyczyść filtry</Button>
            </EmptyState>
          </Card>
        </div>

        <ConfirmDialog
          v-model:open="confirmOpen"
          title="Usuń przepis"
          description="Czy na pewno chcesz usunąć przepis „Rosół z makaronem”? Tej operacji nie można cofnąć."
          confirm-label="Usuń"
          variant="destructive"
          @confirm="confirmOpen = false"
        />
        <ConfirmDialog
          v-model:open="processingOpen"
          title="Usuń przepis"
          description="Czy na pewno chcesz usunąć przepis „Rosół z makaronem”? Tej operacji nie można cofnąć."
          confirm-label="Usuń"
          variant="destructive"
          processing
        />
        <ConfirmDialog
          v-model:open="fieldOpen"
          title="Usuń konto"
          description="Po usunięciu konta wszystkie Twoje dane zostaną trwale skasowane. Podaj hasło, aby potwierdzić."
          confirm-label="Usuń konto"
          variant="destructive"
          @confirm="fieldOpen = false"
        >
          <FormField id="ds-delete-password" label="Hasło" error="Podane hasło jest nieprawidłowe.">
            <template #default="{ id, invalid, describedBy }">
              <Input
                :id="id"
                type="password"
                model-value="zle-haslo"
                autocomplete="current-password"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
              />
            </template>
          </FormField>
        </ConfirmDialog>

        <div class="flex flex-col gap-6 rounded-lg border bg-card p-6">
          <PageHeader as="h2" title="Zupa pomidorowa z makaronem i świeżą bazylią">
            <template #breadcrumbs>
              <a href="#ds-komponenty-aplikacji" class="hover:text-foreground hover:underline">Przepisy</a>
              <span aria-hidden="true"> / </span>
              <span aria-current="page">Zupa pomidorowa</span>
            </template>
            <template #description>
              <CategoryBadge category="dinner" />
            </template>
            <template #actions>
              <Button variant="outline">Edytuj przepis</Button>
              <Button variant="secondary">Kopiuj przepis</Button>
            </template>
          </PageHeader>
        </div>

        <div class="rounded-lg border bg-card p-6">
          <EmptyState :icon="SearchX" title="Brak wyników" />
        </div>
      </section>

      <section aria-labelledby="ds-jadlospis" class="flex flex-col gap-4">
        <h2 id="ds-jadlospis" class="text-lg font-semibold">Jadłospis</h2>

        <div class="flex flex-col gap-2">
          <p class="text-xs text-muted-foreground">Siatka tygodnia (jeden posiłek bez przepisu: „Bez posiłku”, jeden usunięty przepis, jedna lista składników)</p>
          <MealPlanGrid :start-date="planStartDate" :meals="planMeals" />
        </div>

        <div class="flex flex-col gap-2">
          <p class="text-xs text-muted-foreground">Siatka szkicu (tryb edycji, przycisk „Zmień” w każdej komórce, „Wybierz” w pustej; czwartkowy obiad „Na 2 dni” i piątkowy „Z wczoraj”: okno piątku pokazuje „Jak wczoraj … obecny”, okno czwartku ostrzeżenie; piątkowa kolacja „Też na śniadanie” i sobotnie śniadanie „Z kolacji”: okno soboty pokazuje „Z kolacji … obecny”, okno piątkowej kolacji ostrzeżenie)</p>
          <MealPlanGrid :start-date="planStartDate" :meals="planMeals" editable @swap="openSwap" />
          <div>
            <Button variant="outline" @click="openSwap(planMeals[0])">Otwórz okno wyboru przepisu</Button>
          </div>
          <RecipeSwapDialog
            v-model:open="swapOpen"
            :meal-plan-id="1"
            :meal="swapMeal"
            :options="swapOptions"
            :week-meals="planMeals"
            demo
          />
        </div>

        <div class="flex flex-col gap-2">
          <p class="text-xs text-muted-foreground">Siatka w trakcie generowania</p>
          <MealPlanGrid :start-date="planStartDate" skeleton />
        </div>

        <div class="flex flex-col gap-2">
          <p class="text-xs text-muted-foreground">Pobieranie PDF</p>
          <div>
            <PdfDownloadMenu :links="{ pdf: '#pdf', pdf_with_recipes: '#pdf-z-przepisami' }" />
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <div class="flex flex-col gap-2">
            <p class="text-xs text-muted-foreground">Lista zakupów</p>
            <ShoppingList :list="planShoppingList" />
          </div>
          <div class="flex flex-col gap-2">
            <p class="text-xs text-muted-foreground">Lista zakupów bez pozycji</p>
            <ShoppingList :list="emptyShoppingList" />
          </div>
          <div class="flex flex-col gap-2">
            <p class="text-xs text-muted-foreground">Lista zakupów w szkicu (miejsce na listę)</p>
            <ShoppingList pending />
          </div>
        </div>
      </section>
    </main>
  </div>
</template>
