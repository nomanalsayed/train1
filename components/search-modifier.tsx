"use client"

import { useState, useEffect } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { ArrowUpDown, Search } from "lucide-react"
import { useRouter } from "next/navigation"

interface Train {
  id: string
  train_name: string
  train_number: string
  route: string
}

interface Coach {
  id: string
  name: string
  totalSeats: number
}

interface Props {
  currentTrain: Train
  currentFrom: string
  currentTo: string
  currentDirection: string
}

export function SearchModifier({ currentTrain, currentFrom, currentTo, currentDirection }: Props) {
  const [from, setFrom] = useState(currentFrom)
  const [to, setTo] = useState(currentTo)
  const [selectedCoach, setSelectedCoach] = useState<string>("all_coaches")
  const [coaches, setCoaches] = useState<Coach[]>([])
  const router = useRouter()

  useEffect(() => {
    fetchCoaches()
  }, [currentTrain.id])

  const fetchCoaches = async () => {
    try {
      const response = await fetch(`/api/trains/${currentTrain.id}/coaches`)
      const data = await response.json()
      setCoaches(data.coaches || [])
    } catch (error) {
      console.error("Failed to fetch coaches:", error)
    }
  }

  const swapDirection = () => {
    const newFrom = to
    const newTo = from
    setFrom(newFrom)
    setTo(newTo)

    // Handle train number pairing (701 <-> 702)
    const currentNumber = Number.parseInt(currentTrain.train_number)
    const oppositeNumber = currentNumber % 2 === 1 ? currentNumber + 1 : currentNumber - 1

    // Navigate to opposite train if it exists
    router.push(
      `/trains/${currentTrain.id}/direction?from=${newFrom}&to=${newTo}&direction=${newFrom.toLowerCase() === "dhaka" ? "dhaka-panchagarh" : "panchagarh-dhaka"}`,
    )
  }

  const handleSearch = () => {
    const direction = from.toLowerCase() === "dhaka" ? "dhaka-panchagarh" : "panchagarh-dhaka"
    const params = new URLSearchParams({
      from,
      to,
      direction,
      ...(selectedCoach !== "all_coaches" && { coach: selectedCoach }),
    })

    router.push(`/trains/${currentTrain.id}/direction?${params.toString()}`)
  }

  return (
    <Card className="mb-6">
      <CardHeader>
        <CardTitle className="flex items-center space-x-2">
          <Search className="w-5 h-5" />
          <span>Modify Search</span>
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Route Selection */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">From</label>
            <Select value={from} onValueChange={setFrom}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Dhaka">Dhaka</SelectItem>
                <SelectItem value="Panchagarh">Panchagarh</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="flex justify-center">
            <Button variant="outline" size="icon" onClick={swapDirection} className="rounded-full bg-transparent">
              <ArrowUpDown className="w-4 h-4" />
            </Button>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">To</label>
            <Select value={to} onValueChange={setTo}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Dhaka">Dhaka</SelectItem>
                <SelectItem value="Panchagarh">Panchagarh</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Coach (Optional)</label>
          <Select value={selectedCoach} onValueChange={setSelectedCoach}>
            <SelectTrigger>
              <SelectValue placeholder="Select coach" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all_coaches">All Coaches</SelectItem>
              {coaches.map((coach) => (
                <SelectItem key={coach.id} value={coach.id}>
                  {coach.name} ({coach.totalSeats} seats)
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <Button onClick={handleSearch} className="w-full">
          Update Seat Directions
        </Button>
      </CardContent>
    </Card>
  )
}
