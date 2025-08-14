import { Card, CardContent } from '@/components/ui/card'
import { Train } from 'lucide-react'

export default function Loading() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-background via-background to-muted/30 flex items-center justify-center">
      <Card className="w-full max-w-md border-0 shadow-lg bg-card/50 backdrop-blur">
        <CardContent className="p-8">
          <div className="flex flex-col items-center space-y-4">
            <div className="relative">
              <Train className="h-12 w-12 text-primary animate-pulse" />
              <div className="absolute inset-0 h-12 w-12 border-2 border-primary/30 rounded-full animate-spin border-t-transparent"></div>
            </div>
            <div className="text-center space-y-2">
              <h2 className="text-xl font-semibold">Loading...</h2>
              <p className="text-sm text-muted-foreground">
                Getting your train information ready
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
