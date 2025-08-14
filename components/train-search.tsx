'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Search, Train, Clock, MapPin, ArrowRight } from 'lucide-react'
import { searchTrains } from '@/lib/api-client'
import type { Train as TrainType } from '@/lib/types'

export function TrainSearch() {
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<TrainType[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const router = useRouter()

  const handleSearch = async () => {
    if (!query.trim()) {
      setError('Please enter a train name or number')
      return
    }

    setLoading(true)
    setError('')
    
    try {
      const trains = await searchTrains(query)
      setResults(trains)
      if (trains.length === 0) {
        setError('No trains found matching your search')
      }
    } catch (err) {
      setError('Failed to search trains. Please try again.')
      console.error('Search error:', err)
    } finally {
      setLoading(false)
    }
  }

  const handleTrainSelect = (trainId: string) => {
    router.push(`/trains/${trainId}/direction`)
  }

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      handleSearch()
    }
  }

  return (
    <div className="space-y-6">
      {/* Search Input */}
      <div className="flex gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
          <Input
            placeholder="Enter train name or number (e.g., Padma Express, 759)"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyPress={handleKeyPress}
            className="pl-10 h-11"
          />
        </div>
        <Button 
          onClick={handleSearch} 
          disabled={loading}
          className="h-11 px-6"
        >
          {loading ? (
            <div className="flex items-center gap-2">
              <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
              Searching...
            </div>
          ) : (
            <div className="flex items-center gap-2">
              <Search className="h-4 w-4" />
              Search
            </div>
          )}
        </Button>
      </div>

      {/* Error Message */}
      {error && (
        <div className="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
          <p className="text-destructive text-sm">{error}</p>
        </div>
      )}

      {/* Results */}
      {results.length > 0 && (
        <div className="space-y-4">
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <Train className="h-5 w-5" />
            Search Results ({results.length})
          </h3>
          
          <div className="grid gap-4">
            {results.map((train) => (
              <Card 
                key={train.id} 
                className="hover:shadow-lg transition-all duration-200 cursor-pointer border-0 bg-card/50 backdrop-blur"
                onClick={() => handleTrainSelect(train.id)}
              >
                <CardContent className="p-6">
                  <div className="flex items-center justify-between">
                    <div className="space-y-2">
                      <div className="flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-lg">
                          <Train className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                          <h4 className="font-semibold text-lg">{train.name}</h4>
                          <p className="text-sm text-muted-foreground">Train #{train.number}</p>
                        </div>
                      </div>
                      
                      {train.route && (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground ml-11">
                          <MapPin className="h-4 w-4" />
                          <span>{train.route.from}</span>
                          <ArrowRight className="h-4 w-4" />
                          <span>{train.route.to}</span>
                        </div>
                      )}
                      
                      {train.departure_time && (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground ml-11">
                          <Clock className="h-4 w-4" />
                          <span>Departure: {train.departure_time}</span>
                        </div>
                      )}
                    </div>
                    
                    <Button variant="outline" size="sm">
                      View Seats
                      <ArrowRight className="ml-2 h-4 w-4" />
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
