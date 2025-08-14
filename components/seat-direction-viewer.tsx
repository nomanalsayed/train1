'use client'

import { useState, useEffect } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ArrowUp, ArrowDown, Users, Train, AlertCircle, CheckCircle, Home } from 'lucide-react'

import { useRouter } from 'next/navigation'
import { SeatMapVisual } from './seat-map-visual'

interface SeatDirectionViewerProps {
  trainId: string
  trainName?: string
  trainNumber?: string
  from?: string
  to?: string
  filterCoach?: string
  trainData: any
}

interface Coach {
  id: number
  code: string
  type: string
  totalSeats: number
  frontFacingSeats: number[]
  backFacingSeats: number[]
  frontFacingCount: number
  backFacingCount: number
  seatLayout?: { seatNumber: string; isOccupied: boolean; facing: 'forward' | 'backward' }[];
  direction?: 'forward' | 'backward';
  routeCode?: string;
}

export function SeatDirectionViewer({ 
  trainId, 
  trainName, 
  trainNumber, 
  from, 
  to, 
  filterCoach,
  trainData 
}: SeatDirectionViewerProps) {
  const [coaches, setCoaches] = useState<Coach[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const router = useRouter()

  useEffect(() => {
    const fetchCoaches = async () => {
      try {
        setLoading(true)
        setError(null)

        console.log('Fetching coaches from API...')

        // Construct the route code from 'from' and 'to' if available
        const routeCode = (from && to) ? `${from.toUpperCase()}→${to.toUpperCase()}` : null;

        // Fetch coaches based on trainId and routeCode
        const apiUrl = routeCode 
          ? `/api/trains/${trainId}/route/${encodeURIComponent(routeCode)}/coaches`
          : `/api/trains/${trainId}/coaches`;

        const response = await fetch(apiUrl);

        console.log('Coaches API response status:', response.status)

        if (!response.ok) {
          throw new Error(`Failed to fetch coaches: ${response.status}`)
        }

        const data = await response.json()
        console.log('Coaches API data:', data)

        const coachesData = data.coaches || []
        setCoaches(coachesData)
      } catch (err) {
        console.error('Coach fetch error:', err)
        setError('Failed to load coach information')
      } finally {
        setLoading(false)
      }
    }

    if (trainId && trainId !== "undefined") {
      fetchCoaches()
    } else {
      setLoading(false)
      setError('Train ID is missing')
    }
  }, [trainId, from, to]) // Add from and to as dependencies

  const getSeatDirection = (coach: Coach) => {
    const frontCount = coach.frontFacingCount || coach.frontFacingSeats?.length || 0
    const backCount = coach.backFacingCount || coach.backFacingSeats?.length || 0

    if (frontCount > 0 && backCount === 0) return 'forward'
    if (backCount > 0 && frontCount === 0) return 'backward'
    if (frontCount > 0 && backCount > 0) return 'mixed'
    return 'unknown'
  }

  const getDirectionIcon = (direction: string) => {
    switch (direction) {
      case 'forward':
        return <ArrowUp className="h-4 w-4 text-green-600" />
      case 'backward':
        return <ArrowDown className="h-4 w-4 text-red-600" />
      case 'mixed':
        return <AlertCircle className="h-4 w-4 text-amber-600" />
      default:
        return <AlertCircle className="h-4 w-4 text-muted-foreground" />
    }
  }

  const getDirectionColor = (direction: string) => {
    switch (direction) {
      case 'forward':
        return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400'
      case 'backward':
        return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400'
      case 'mixed':
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400'
      default:
        return 'bg-muted text-muted-foreground'
    }
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 flex items-center justify-center px-4">
        <div className="text-center bg-white rounded-2xl p-8 shadow-lg border border-emerald-100">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600 mx-auto mb-4"></div>
          <h3 className="text-lg font-semibold text-gray-900 mb-2">
            Loading Coach Information
          </h3>
          <p className="text-gray-600 text-sm">
            Fetching seat direction data...
          </p>
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-red-50 to-orange-50 flex items-center justify-center px-4">
        <div className="text-center max-w-md mx-auto bg-white rounded-2xl p-8 shadow-lg border border-red-100">
          <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <AlertCircle className="w-8 h-8 text-red-600" />
          </div>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">
            Failed to Load Coach Data
          </h2>
          <p className="text-red-600 mb-6 text-sm">{error}</p>
          <Button
            onClick={() => router.push("/")}
            variant="outline"
            className="w-full bg-white hover:bg-gray-50 border-2 border-emerald-200 text-emerald-700 font-medium py-3"
          >
            <Home className="w-4 h-4 mr-2" />
            Back to Home
          </Button>
        </div>
      </div>
    )
  }

  if (coaches.length === 0) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-amber-50 to-orange-50 flex items-center justify-center px-4">
        <div className="text-center max-w-md mx-auto bg-white rounded-2xl p-8 shadow-lg border border-amber-100">
          <div className="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Train className="w-8 h-8 text-amber-600" />
          </div>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">
            No Coach Information Available
          </h2>
          <p className="text-amber-600 mb-6 text-sm">
            Coach seat direction data is not available for this train.
          </p>
          <Button
            onClick={() => router.push("/")}
            variant="outline"
            className="w-full bg-white hover:bg-gray-50 border-2 border-emerald-200 text-emerald-700 font-medium py-3"
          >
            <Home className="w-4 h-4 mr-2" />
            Back to Home
          </Button>
        </div>
      </div>
    )
  }

  // Filter coaches if filterCoach is provided
  const filteredCoaches = filterCoach 
    ? coaches.filter(coach => coach.code.toUpperCase() === filterCoach.toUpperCase())
    : coaches

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 p-4">
      <div className="max-w-4xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">
                {trainName || `Train ${trainNumber || trainId}`}
              </h1>
              {from && to && (
                <p className="text-lg text-gray-600 mt-1">
                  {from} → {to}
                </p>
              )}
            </div>
            <Button
              onClick={() => router.push("/")}
              variant="outline"
              className="bg-white hover:bg-gray-50 border-2 border-emerald-200 text-emerald-700 font-medium"
            >
              <Home className="w-4 h-4 mr-2" />
              Home
            </Button>
          </div>

          {filterCoach && (
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <p className="text-blue-800 font-medium">
                Showing information for Coach: <span className="font-bold">{filterCoach}</span>
              </p>
            </div>
          )}
        </div>

        {/* Legend */}
        <Card className="mb-6">
          <CardHeader>
            <CardTitle className="text-lg">Seat Direction Legend</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex flex-wrap gap-4">
              <div className="flex items-center gap-2">
                <ArrowUp className="h-4 w-4 text-green-600" />
                <Badge className="bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                  Forward Facing
                </Badge>
              </div>
              <div className="flex items-center gap-2">
                <ArrowDown className="h-4 w-4 text-red-600" />
                <Badge className="bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400">
                  Backward Facing
                </Badge>
              </div>
              <div className="flex items-center gap-2">
                <AlertCircle className="h-4 w-4 text-amber-600" />
                <Badge className="bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
                  Mixed Directions
                </Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Coaches */}
        <div className="grid gap-4">
          {filteredCoaches.map((coach) => {
            const direction = getSeatDirection(coach)
            const frontCount = coach.frontFacingCount || coach.frontFacingSeats?.length || 0
            const backCount = coach.backFacingCount || coach.backFacingSeats?.length || 0

            return (
              <Card key={coach.id} className="hover:shadow-lg transition-shadow">
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="p-2 bg-primary/10 rounded-lg">
                        <Train className="h-5 w-5 text-primary" />
                      </div>
                      <div>
                        <CardTitle className="text-lg">Coach {coach.code}</CardTitle>
                        <CardDescription>{coach.type || 'Standard'} Class</CardDescription>
                      </div>
                    </div>
                    <Badge className={getDirectionColor(direction)}>
                      <div className="flex items-center gap-1">
                        {getDirectionIcon(direction)}
                        {direction.charAt(0).toUpperCase() + direction.slice(1)}
                      </div>
                    </Badge>
                  </div>
                </CardHeader>

                <CardContent>
                  <div className="space-y-4">
                    {coach.totalSeats > 0 && (
                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Users className="h-4 w-4" />
                        <span>Total Seats: {coach.totalSeats}</span>
                      </div>
                    )}

                    {coach.seatLayout && coach.seatLayout.length > 0 ? (
                      <SeatMapVisual 
                        seatLayout={coach.seatLayout}
                        coachCode={coach.code}
                        direction={coach.direction || 'forward'}
                        routeCode={coach.routeCode}
                      />
                    ) : (
                      <div className="grid gap-4 md:grid-cols-2">
                        <Card>
                          <CardHeader>
                            <CardTitle className="text-emerald-700">Front-Facing Seats</CardTitle>
                          </CardHeader>
                          <CardContent>
                            <div className="grid grid-cols-10 gap-1">
                              {(coach.frontFacingSeats || []).map((seatNum) => (
                                <div
                                  key={seatNum}
                                  className="w-8 h-8 bg-emerald-100 border border-emerald-300 rounded text-xs flex items-center justify-center font-medium text-emerald-800"
                                >
                                  {seatNum}
                                </div>
                              ))}
                            </div>
                            <p className="text-sm text-gray-600 mt-2">
                              {coach.frontFacingCount || 0} seats facing direction of travel
                            </p>
                          </CardContent>
                        </Card>

                        <Card>
                          <CardHeader>
                            <CardTitle className="text-gray-700">Back-Facing Seats</CardTitle>
                          </CardHeader>
                          <CardContent>
                            <div className="grid grid-cols-10 gap-1">
                              {(coach.backFacingSeats || []).map((seatNum) => (
                                <div
                                  key={seatNum}
                                  className="w-8 h-8 bg-gray-100 border border-gray-300 rounded text-xs flex items-center justify-center font-medium text-gray-800"
                                >
                                  {seatNum}
                                </div>
                              ))}
                            </div>
                            <p className="text-sm text-gray-600 mt-2">
                              {coach.backFacingCount || 0} seats facing opposite to direction of travel
                            </p>
                          </CardContent>
                        </Card>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            )
          })}
        </div>
      </div>
    </div>
  )
}