import { useEffect, useState } from "react";
import { Line } from "react-chartjs-2";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Legend,
} from "chart.js";
import api from "../services/api";

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend);

export default function OrdersByMonthChart() {
  const [labels, setLabels] = useState([]);
  const [values, setValues] = useState([]);

  useEffect(() => {
    api.get("/stats/orders-by-month")
      .then((res) => {
        setLabels(res.data.labels || []);
        setValues(res.data.values || []);
      })
      .catch((e) => {
        console.error("Stats fetch failed:", e);
      });
  }, []);

  const data = {
    labels,
    datasets: [
      {
        label: "Broj porudžbina",
        data: values,
      },
    ],
  };

  return (
    <div style={{ maxWidth: 900 }}>
      <h2>Porudžbine po mesecima</h2>
      <Line data={data} />
    </div>
  );
}