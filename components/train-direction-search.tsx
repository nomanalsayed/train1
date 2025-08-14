"use client"

import { useState, useCallback, useMemo } from "react"
import { Button } from "@/components/ui/button"
import { ArrowUpDown, Search, Train, MapPin } from "lucide-react"
import { StationSearch } from "./station-search"
import { CoachSelection } from "./coach-selection"

interface TrainResult {
  id: string
  name: string
  number: string
  from_station: string
  to_station: string
}

/**
 * PURPOSE: Main search interface for finding trains and seat directions
 *
 * This is the primary search component that allows users to:
 * - Search trains by route (from/to stations) or by train name/number
 * - Select coach preferences
 * - View search results with train schedules
 * - Navigate to seat direction guides or seat maps
 * - Handle all search logic and form validation
 */
export function TrainDirectionSearch() {
  const [searchType, setSearchType] = useState<"route" | "train">("route")
  const [fromStation, setFromStation] = useState("")
  const [toStation, setToStation] = useState("")
  const [selectedCoach, setSelectedCoach] = useState("")
  const [trainQuery, setTrainQuery] = useState("")
  const [results, setResults] = useState<TrainResult[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState("")
  const [showStationSearch, setShowStationSearch] = useState<"from" | "to" | null>(null)
  const [showCoachSelection, setShowCoachSelection] = useState(false)

  const handleSearch = useCallback(async () => {
    if (searchType === "route" && (!fromStation || !toStation)) {
      setError("Please select both departure and destination stations")
      return
    }
    if (searchType === "train" && !trainQuery.trim()) {
      setError("Please enter a train name or number")
      return
    }

    setLoading(true)
    setError("")

    try {
      const url =
        searchType === "route"
          ? `/api/trains/search?from=${encodeURIComponent(fromStation)}&to=${encodeURIComponent(toStation)}`
          : `/api/trains/search?query=${encodeURIComponent(trainQuery.trim())}`

      const response = await fetch(url)
      if (!response.ok) throw new Error("Search failed")

      const data = await response.json()

      const formattedResults =
        data.trains?.map((train: any) => ({
          id: train.id,
          name: train.name,
          number: train.number || train.id || train.train_number || String(train.id),
          from_station:
            typeof train.from_station === "object"
              ? train.from_station?.title || train.from_station?.name || fromStation
              : train.from_station || fromStation,
          to_station:
            typeof train.to_station === "object"
              ? train.to_station?.title || train.to_station?.name || toStation
              : train.to_station || toStation,
        })) || []

      setResults(formattedResults)

      if (!formattedResults.length) {
        setError("No trains found. Please try different search criteria.")
      }
    } catch (error) {
      setError("Unable to search trains. Please check your connection and try again.")
      console.error("Search failed:", error)
    } finally {
      setLoading(false)
    }
  }, [searchType, fromStation, toStation, trainQuery])

  // Optimized station swap
  const swapStations = useCallback(() => {
    setFromStation(toStation)
    setToStation(fromStation)
    setError("")
  }, [fromStation, toStation])

  // Memoized validation
  const canSearch = useMemo(() => {
    return searchType === "route" ? fromStation && toStation : trainQuery.trim().length > 0
  }, [searchType, fromStation, toStation, trainQuery])

  // Station search handler
  const handleStationSelect = useCallback(
    (station: string) => {
      if (showStationSearch === "from") {
        setFromStation(station)
      } else {
        setToStation(station)
      }
      setShowStationSearch(null)
      setError("")
    },
    [showStationSearch],
  )

  // Coach selection handler
  const handleCoachSelect = useCallback((coach: string) => {
    setSelectedCoach(coach)
    setShowCoachSelection(false)
  }, [])

  // Navigation handlers
  if (showStationSearch) {
    return <StationSearch onSelect={handleStationSelect} onBack={() => setShowStationSearch(null)} />
  }

  if (showCoachSelection) {
    return (
      <CoachSelection
        fromStation={fromStation}
        toStation={toStation}
        onSelect={handleCoachSelect}
        onBack={() => setShowCoachSelection(false)}
      />
    )
  }

  // Results view
  if (results.length > 0) {
    return (
      <div className="space-y-4">
        {/* Header with back action */}
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-xl font-bold text-gray-900">Available Trains</h2>
            <p className="text-sm text-gray-600 mt-1">
              {searchType === "route" ? `${fromStation} → ${toStation}` : `Search: ${trainQuery}`}
            </p>
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setResults([])
              setError("")
            }}
            className="text-emerald-700 hover:bg-emerald-50 font-medium rounded-md"
          >
            New Search
          </Button>
        </div>

        {/* Results list */}
        <div className="space-y-3">
          {results.map((train, index) => (
            <div key={train.id}>
              <div className="bg-white rounded-md p-5 border border-gray-200 hover:border-emerald-300 transition-colors">
                {/* Train header */}
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <h3 className="font-bold text-gray-900 text-lg">{train.name.toUpperCase()}</h3>
                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 mt-1">
                      #{train.number}
                    </span>
                  </div>
                </div>

                {/* Journey details */}
                <div className="flex items-center justify-between mb-5">
                  <div className="text-center flex-1">
                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-1 font-medium">From</div>
                    <div className="font-bold text-gray-900 text-sm">{train.from_station}</div>
                  </div>

                  <div className="flex-1 flex items-center justify-center px-4">
                    <div className="text-center">
                      <div className="w-16 h-0.5 bg-emerald-200 mb-2 relative">
                        <div className="absolute left-0 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-emerald-500 rounded-full"></div>
                        <div className="absolute right-0 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-emerald-500 rounded-full"></div>
                      </div>
                    </div>
                  </div>

                  <div className="text-center flex-1">
                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-1 font-medium">To</div>
                    <div className="font-bold text-gray-900 text-sm">{train.to_station}</div>
                  </div>
                </div>

                {/* Action button */}
                <Button
                  onClick={() => {
                    const trainIdentifier = train.number || train.id
                    if (!trainIdentifier) {
                      console.error("No train identifier available for navigation:", train)
                      return
                    }
                    const params = new URLSearchParams({
                      from: String(train.from_station),
                      to: String(train.to_station),
                      trainName: String(train.name),
                    })

                    if (selectedCoach) {
                      params.set("coach", selectedCoach)
                    }

                    window.location.href = `/trains/${trainIdentifier}/seats?${params.toString()}`
                  }}
                  className="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-md font-semibold transition-colors"
                >
                  <Train className="w-4 h-4 mr-2" />
                  View Seat Map
                </Button>
              </div>
            </div>
          ))}
        </div>
      </div>
    )
  }

  // Main search form
  return (
    <div className="bg-white rounded-md border border-gray-200 p-6">
      {/* Search Type Toggle */}
      <div className="flex bg-gray-100 rounded-md p-1 mb-6">
        <button
          onClick={() => {
            setSearchType("route")
            setError("")
          }}
          className={`flex-1 py-3 px-4 rounded-sm text-sm font-medium transition-all duration-200 ${
            searchType === "route"
              ? "bg-white text-emerald-700"
              : "text-gray-600 hover:text-emerald-600"
          }`}
        >
          <div className="flex items-center justify-center space-x-2">
            <MapPin className="w-4 h-4" />
            <span>By Route</span>
          </div>
        </button>
        <button
          onClick={() => {
            setSearchType("train")
            setError("")
          }}
          className={`flex-1 py-3 px-4 rounded-sm text-sm font-medium transition-all duration-200 ${
            searchType === "train"
              ? "bg-white text-emerald-700"
              : "text-gray-600 hover:text-emerald-600"
          }`}
        >
          <div className="flex items-center justify-center space-x-2">
            <Train className="w-4 h-4" />
            <span>By Train</span>
          </div>
        </button>
      </div>

      <div className="p-6 space-y-5">
        {/* Error message */}
        {error && (
          <div className="bg-red-50 rounded-md p-4">
            <p className="text-red-700 text-sm font-medium">{error}</p>
          </div>
        )}

        {searchType === "route" ? (
          <>
            {/* From Station */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-2">From Station</label>
              <button
                onClick={() => setShowStationSearch("from")}
                className="w-full p-4 text-left rounded-md bg-gray-50 border border-gray-200 hover:bg-gray-100 focus:border-emerald-500 focus:outline-none transition-colors group"
              >
                <div className="flex items-center justify-between">
                  <span className={`font-medium ${fromStation ? "text-gray-900" : "text-gray-500"}`}>
                    {fromStation || "Select departure station"}
                  </span>
                  <MapPin className="w-4 h-4 text-gray-400 group-hover:text-emerald-500 transition-colors" />
                </div>
              </button>
            </div>

            {/* Swap Button */}
            <div className="flex justify-center">
              <button
                onClick={swapStations}
                disabled={!fromStation && !toStation}
                className="p-3 rounded-full bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition-all duration-200 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <ArrowUpDown className="w-5 h-5" />
              </button>
            </div>

            {/* To Station */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-2">To Station</label>
              <button
                onClick={() => setShowStationSearch("to")}
                className="w-full p-4 text-left rounded-md bg-gray-50 border border-gray-200 hover:bg-gray-100 focus:border-emerald-500 focus:outline-none transition-colors group"
              >
                <div className="flex items-center justify-between">
                  <span className={`font-medium ${toStation ? "text-gray-900" : "text-gray-500"}`}>
                    {toStation || "Select destination station"}
                  </span>
                  <MapPin className="w-4 h-4 text-gray-400 group-hover:text-emerald-500 transition-colors" />
                </div>
              </button>
            </div>

            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-2">Coach (Optional)</label>
              <button
                onClick={() => setShowCoachSelection(true)}
                className="w-full p-4 text-left rounded-md bg-gray-50 border border-gray-200 hover:bg-gray-100 focus:border-emerald-500 focus:outline-none transition-colors group"
              >
                <div className="flex items-center justify-between">
                  <span className={`font-medium ${selectedCoach ? "text-gray-900" : "text-gray-500"}`}>
                    {selectedCoach || "Select coach (optional)"}
                  </span>
                  <div className="w-4 h-4 text-gray-400 group-hover:text-emerald-500 transition-colors">▼</div>
                </div>
              </button>
            </div>
          </>
        ) : (
          <>
            {/* Train Name or Number */}
            <div className="relative">
              <label className="block text-sm font-semibold text-gray-700 mb-2">Train Name or Number</label>
              <input
                type="text"
                placeholder="e.g., Suborno Express, 701"
                value={trainQuery}
                onChange={(e) => {
                  setTrainQuery(e.target.value)
                  setError("")
                }}
                className="w-full p-4 pl-12 rounded-md bg-gray-50 border border-gray-200 focus:border-emerald-500 focus:outline-none transition-colors"
              />
              <Train className="absolute left-4 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
            </div>

            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-2">Coach (Optional)</label>
              <button
                onClick={() => setShowCoachSelection(true)}
                className="w-full p-4 text-left rounded-md bg-gray-50 border border-gray-200 hover:bg-gray-100 focus:border-emerald-500 focus:outline-none transition-colors group"
              >
                <div className="flex items-center justify-between">
                  <span className={`font-medium ${selectedCoach ? "text-gray-900" : "text-gray-500"}`}>
                    {selectedCoach || "Select coach (optional)"}
                  </span>
                  <div className="w-4 h-4 text-gray-400 group-hover:text-emerald-500 transition-colors">▼</div>
                </div>
              </button>
            </div>
          </>
        )}

        {/* Search Button */}
        <Button
          onClick={handleSearch}
          disabled={loading || !canSearch}
          className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-4 px-6 rounded-md disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {loading ? (
            <div className="flex items-center justify-center space-x-2">
              <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
              <span>Searching...</span>
            </div>
          ) : (
            <div className="flex items-center justify-center space-x-2">
              <Search className="w-4 h-4" />
              <span>Find Seat Directions</span>
            </div>
          )}
        </Button>
      </div>
    </div>
  )
}